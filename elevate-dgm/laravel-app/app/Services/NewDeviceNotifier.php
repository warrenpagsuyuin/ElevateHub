<?php

namespace App\Services;

use App\Mail\NewDeviceLoginMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Remembers browsers that have signed in (random token in an encrypted, HttpOnly cookie;
 * only its hash is stored) and emails the user when an unrecognised device signs in.
 * The very first sign-in for an account is not reported, to avoid noise.
 */
class NewDeviceNotifier
{
    public const COOKIE = 'elevate_device';

    public function handle(User $user, Request $request): void
    {
        $token = (string) $request->cookie(self::COOKIE);

        if (strlen($token) !== 64) {
            $token = Str::random(64);
        }

        $hash = hash_hmac('sha256', $token, (string) config('app.key'));
        $known = DB::table('user_devices')->where('user_id', $user->id)->where('token_hash', $hash)->exists();
        $hasAny = $known || DB::table('user_devices')->where('user_id', $user->id)->exists();
        $now = now();
        $agent = Str::limit((string) $request->userAgent(), 250, '');

        DB::table('user_devices')->upsert([[
            'user_id' => $user->id,
            'token_hash' => $hash,
            'user_agent' => $agent,
            'ip' => $request->ip(),
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id', 'token_hash'], ['user_agent', 'ip', 'last_seen_at', 'updated_at']);

        Cookie::queue(Cookie::make(self::COOKIE, $token, 60 * 24 * 400, null, null, null, true, false, 'lax'));

        if (! $known && $hasAny && config('otp.new_device_alerts', true)) {
            try {
                Mail::to($user->email)->queue(
                    (new NewDeviceLoginMail($agent ?: 'Unknown browser', (string) $request->ip(), $now->utc()->format('d M Y, H:i').' UTC'))
                        ->onConnection(config('otp.queue_connection'))
                        ->onQueue(config('otp.queue'))
                );
            } catch (Throwable) {
                // An alert must never block sign-in.
            }
        }
    }
}
