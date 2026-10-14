<?php

namespace App\Services;

use App\Jobs\SendOtpMail;
use App\Models\User;
use App\Support\OtpAudit;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Single home for email one-time-password logic.
 *
 * Security properties:
 *  - codes come from random_int (CSPRNG) and only an HMAC-SHA256 (peppered) is stored;
 *  - comparison uses hash_equals; consumption is an atomic conditional UPDATE inside
 *    the same transaction as the sign-in callback, so parallel requests can't both win;
 *  - a new code replaces/invalidates every earlier code for the same email;
 *  - unknown emails get a "decoy" challenge that never matches and never sends mail,
 *    so responses look identical to registered accounts.
 */
class OtpService
{
    public const RESULT_OK = 'ok';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_EXPIRED = 'expired';
    public const RESULT_LOCKED = 'locked';
    public const RESULT_REJECTED = 'rejected';
    public const RESEND_OK = 'sent';
    public const RESEND_COOLDOWN = 'cooldown';
    public const RESEND_LIMIT = 'limit';

    public function ttl(): int
    {
        return max(30, (int) config('otp.ttl_seconds', 300));
    }

    /**
     * Start (or continue) a challenge for an email. Returns the challenge id.
     * Re-submitting the email during the resend cooldown reuses the current code
     * instead of sending another one, so the email step can't bypass resend limits.
     */
    public function issue(string $email, ?User $user, ?string $ip): int
    {
        $emailHash = $this->emailHash($email);
        $latest = $this->latestFor($emailHash);
        $now = now();

        if ($latest && $this->isOpen($latest) && $latest->resend_available_at && Carbon::parse($latest->resend_available_at)->isFuture()) {
            return (int) $latest->id;
        }

        [$resendCount, $windowStart] = $this->windowFor($latest);

        if ($latest && $resendCount >= $this->maxResends() && $this->isOpen($latest)) {
            return (int) $latest->id;
        }

        $code = $this->generateCode();
        $expiresAt = $now->copy()->addSeconds($this->ttl());

        $id = DB::transaction(function () use ($emailHash, $user, $ip, $code, $expiresAt, $now, $resendCount, $windowStart) {
            $this->invalidateOpen($emailHash);

            return DB::table('otp_challenges')->insertGetId([
                'user_id' => $user?->id,
                'email_hash' => $emailHash,
                // Decoys store a hash of random bytes: no 6-digit input can ever match.
                'code_hash' => $user ? $this->hashCode($code) : hash('sha256', random_bytes(32)),
                'generation' => 1,
                'attempts' => 0,
                'resend_count' => $resendCount,
                'resend_window_started_at' => $windowStart,
                'resend_available_at' => $now->copy()->addSeconds($this->cooldown()),
                'expires_at' => $expiresAt,
                'ip' => $ip,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $this->deliver($id, 1, $user, $code, $expiresAt);
        OtpAudit::record('otp.sent', $user, $emailHash, $ip);

        return $id;
    }

    /**
     * Replace the code on an existing challenge (server-enforced cooldown and window cap).
     *
     * @return array{status: string, challenge_id: int}
     */
    public function resend(int $challengeId, ?string $ip): array
    {
        $result = DB::transaction(function () use ($challengeId, $ip) {
            $challenge = DB::table('otp_challenges')->where('id', $challengeId)->lockForUpdate()->first();

            if (! $challenge || $challenge->consumed_at) {
                return ['status' => self::RESULT_REJECTED, 'challenge_id' => $challengeId];
            }

            if ($challenge->resend_available_at && Carbon::parse($challenge->resend_available_at)->isFuture()) {
                return ['status' => self::RESEND_COOLDOWN, 'challenge_id' => $challengeId];
            }

            [$resendCount, $windowStart] = $this->windowFor($challenge);

            if ($resendCount >= $this->maxResends()) {
                return [
                    'status' => self::RESEND_LIMIT,
                    'challenge_id' => $challengeId,
                    'user_id' => $challenge->user_id,
                    'email_hash' => $challenge->email_hash,
                ];
            }

            $user = $challenge->user_id ? User::find($challenge->user_id) : null;
            $code = $this->generateCode();
            $now = now();
            $expiresAt = $now->copy()->addSeconds($this->ttl());
            $generation = (int) $challenge->generation + 1;
            $this->invalidateOpen($challenge->email_hash, (int) $challenge->id);

            DB::table('otp_challenges')->where('id', $challenge->id)->update([
                'code_hash' => $user ? $this->hashCode($code) : hash('sha256', random_bytes(32)),
                'generation' => $generation,
                'attempts' => 0,
                'locked_until' => null,
                'invalidated_at' => null,
                'delivered_at' => null,
                'delivery_failed_at' => null,
                'resend_count' => $resendCount + 1,
                'resend_window_started_at' => $windowStart,
                'resend_available_at' => $now->copy()->addSeconds($this->cooldown()),
                'expires_at' => $expiresAt,
                'ip' => $ip,
                'updated_at' => $now,
            ]);

            return [
                'status' => self::RESEND_OK,
                'challenge_id' => (int) $challenge->id,
                'generation' => $generation,
                'user' => $user,
                'code' => $code,
                'expires_at' => $expiresAt,
                'email_hash' => $challenge->email_hash,
            ];
        });

        if ($result['status'] === self::RESEND_LIMIT) {
            OtpAudit::record('otp.resend_limited', $result['user_id'], $result['email_hash'], $ip);
        }

        if ($result['status'] !== self::RESEND_OK) {
            return ['status' => $result['status'], 'challenge_id' => $result['challenge_id']];
        }

        $this->deliver(
            $result['challenge_id'],
            $result['generation'],
            $result['user'],
            $result['code'],
            $result['expires_at']
        );
        OtpAudit::record('otp.resent', $result['user'], $result['email_hash'], $ip);

        return ['status' => self::RESEND_OK, 'challenge_id' => $result['challenge_id']];
    }

    /**
     * Verify a code. $onSuccess runs inside the consuming transaction with the user;
     * return false from it to refuse sign-in (the code stays consumed).
     *
     * @param  Closure(User): bool  $onSuccess
     * @return array{status: string, user: ?User}
     */
    public function verify(int $challengeId, string $code, ?string $ip, Closure $onSuccess): array
    {
        return DB::transaction(function () use ($challengeId, $code, $ip, $onSuccess) {
            $challenge = DB::table('otp_challenges')->where('id', $challengeId)->lockForUpdate()->first();
            $now = now();

            if (! $challenge || $challenge->consumed_at || $challenge->invalidated_at && ! $challenge->locked_until) {
                return ['status' => self::RESULT_EXPIRED, 'user' => null];
            }

            if ($challenge->locked_until && Carbon::parse($challenge->locked_until)->isFuture()
                || $challenge->attempts >= $this->maxAttempts()) {
                return ['status' => self::RESULT_LOCKED, 'user' => null];
            }

            if ($challenge->invalidated_at || Carbon::parse($challenge->expires_at)->lessThanOrEqualTo($now)) {
                return ['status' => self::RESULT_EXPIRED, 'user' => null];
            }

            $matches = preg_match('/^\d{6}$/', $code) === 1
                && hash_equals((string) $challenge->code_hash, $this->hashCode($code));

            if (! $matches || ! $challenge->user_id) {
                $attempts = (int) $challenge->attempts + 1;
                $exhausted = $attempts >= $this->maxAttempts();

                DB::table('otp_challenges')->where('id', $challenge->id)->update(array_filter([
                    'attempts' => $attempts,
                    'invalidated_at' => $exhausted ? $now : null,
                    'locked_until' => $exhausted ? $now->copy()->addSeconds($this->lockout()) : null,
                    'updated_at' => $now,
                ], fn ($value) => $value !== null));

                OtpAudit::record($exhausted ? 'otp.locked_out' : 'otp.failed', $challenge->user_id, $challenge->email_hash, $ip);

                return ['status' => $exhausted ? self::RESULT_LOCKED : self::RESULT_INVALID, 'user' => null];
            }

            // Atomic single-use guard: only one request can flip consumed_at.
            $consumed = DB::table('otp_challenges')
                ->where('id', $challenge->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now, 'updated_at' => $now]);

            $user = User::find($challenge->user_id);

            if ($consumed !== 1 || ! $user) {
                return ['status' => self::RESULT_EXPIRED, 'user' => null];
            }

            if (! $onSuccess($user)) {
                OtpAudit::record('otp.rejected', $user, $challenge->email_hash, $ip);

                return ['status' => self::RESULT_REJECTED, 'user' => $user];
            }

            OtpAudit::record('otp.verified', $user, $challenge->email_hash, $ip);

            return ['status' => self::RESULT_OK, 'user' => $user];
        });
    }

    /** Invalidate every open code for an email (used by "Use another email"). */
    public function cancel(int $challengeId): void
    {
        DB::table('otp_challenges')->where('id', $challengeId)->whereNull('consumed_at')
            ->update(['invalidated_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Everything the UI needs; the client never guesses.
     *
     * @return array<string, mixed>
     */
    public function state(int $challengeId, string $email): array
    {
        $challenge = $this->find($challengeId);
        $iso = fn ($value) => $value ? Carbon::parse($value)->utc()->toIso8601ZuluString() : null;
        [$resendCount, $windowStart] = $this->windowFor($challenge);
        $resendLimitResetsAt = $resendCount >= $this->maxResends()
            ? Carbon::parse($windowStart)->addSeconds($this->window())
            : null;
        $expired = ! $challenge || $challenge->consumed_at || ($challenge->invalidated_at && ! $challenge->locked_until);

        return [
            'masked_email' => self::maskEmail($email),
            'server_now' => now()->utc()->toIso8601ZuluString(),
            'expires_at' => $expired ? now()->utc()->toIso8601ZuluString() : $iso($challenge->expires_at),
            'resend_available_at' => $iso($challenge?->resend_available_at),
            'resend_limit_resets_at' => $iso($resendLimitResetsAt),
            'resends_left' => max(0, $this->maxResends() - $resendCount),
            'attempts_left' => $challenge ? max(0, $this->maxAttempts() - (int) $challenge->attempts) : 0,
            'locked_until' => $iso($challenge?->locked_until),
            'delivery_failed' => (bool) $challenge?->delivery_failed_at,
            'ttl_seconds' => $this->ttl(),
        ];
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $length = mb_strlen($local);
        $masked = $length <= 2
            ? mb_substr($local, 0, 1).str_repeat('*', max(1, $length - 1))
            : mb_substr($local, 0, 1).str_repeat('*', $length - 2).mb_substr($local, -1);

        return $masked.'@'.$domain;
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), $this->pepper());
    }

    public function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, $this->pepper());
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function deliver(int $id, int $generation, ?User $user, string $code, Carbon $expiresAt): void
    {
        try {
            // Decoys dispatch too (recipient null => job no-ops) to keep timing similar.
            SendOtpMail::dispatch($id, $generation, $user?->email, $code, $expiresAt->toIso8601String());
        } catch (Throwable) {
            // Never report the exception: transport errors can echo the message body.
            DB::table('otp_challenges')->where('id', $id)->update(['delivery_failed_at' => now()]);
        }
    }

    private function invalidateOpen(string $emailHash, ?int $exceptId = null): void
    {
        DB::table('otp_challenges')
            ->where('email_hash', $emailHash)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{0: int, 1: Carbon|string} */
    private function windowFor(?object $challenge): array
    {
        $now = now();

        if (! $challenge || ! $challenge->resend_window_started_at
            || Carbon::parse($challenge->resend_window_started_at)->addSeconds($this->window())->isPast()) {
            return [0, $now];
        }

        return [(int) $challenge->resend_count, $challenge->resend_window_started_at];
    }

    private function isOpen(object $challenge): bool
    {
        return ! $challenge->consumed_at && ! $challenge->invalidated_at
            && Carbon::parse($challenge->expires_at)->isFuture();
    }

    private function latestFor(string $emailHash): ?object
    {
        return DB::table('otp_challenges')->where('email_hash', $emailHash)->orderByDesc('id')->first();
    }

    private function find(int $id): ?object
    {
        return DB::table('otp_challenges')->where('id', $id)->first();
    }

    private function pepper(): string
    {
        return (string) config('otp.pepper') ?: (string) config('app.key');
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('otp.max_attempts', 5));
    }

    private function maxResends(): int
    {
        return max(0, (int) config('otp.max_resends', 3));
    }

    private function cooldown(): int
    {
        return max(0, (int) config('otp.resend_cooldown_seconds', 60));
    }

    private function window(): int
    {
        return max(60, (int) config('otp.resend_window_seconds', 900));
    }

    private function lockout(): int
    {
        return max(0, (int) config('otp.lockout_seconds', 300));
    }
}
