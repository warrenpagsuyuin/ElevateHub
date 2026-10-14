<?php

namespace App\Jobs;

use App\Mail\OtpMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendOtpMail implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int|string $challengeId,
        public int $generation,
        public ?string $recipient,
        public string $otp,
        public string $expiresAt,
    ) {
        $this->onConnection(config('otp.queue_connection', 'database'));
        $this->onQueue(config('otp.queue', 'otp'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public static function assertSafeDeliveryConfiguration(): void
    {
        if (app()->isProduction() && config('queue.connections.'.config('otp.queue_connection').'.driver') === 'sync') {
            throw new RuntimeException('OTP delivery requires an asynchronous queue.');
        }
        $visited = [];
        $inspect = function (string $name) use (&$inspect, &$visited): void {
            if (isset($visited[$name])) {
                return;
            }
            $visited[$name] = true;
            $mailer = config('mail.mailers.'.$name, []);
            if (($mailer['transport'] ?? '') === 'log'
                && !(app()->environment('local') && config('otp.allow_local_log', false))) {
                throw new RuntimeException('OTP logging is disabled.');
            }
            foreach ($mailer['mailers'] ?? [] as $child) {
                $inspect($child);
            }
        };
        $inspect((string) config('otp.mailer', config('mail.default')));
    }

    public function handle(): void
    {
        if ($this->recipient === null) {
            return;
        }
        if (!$this->currentChallenge()->whereNull('consumed_at')->whereNull('invalidated_at')->whereNull('delivered_at')->where('expires_at', '>', now())->exists()) {
            return;
        }
        try {
            self::assertSafeDeliveryConfiguration();
            Mail::mailer(config('otp.mailer', config('mail.default')))
                ->to($this->recipient)->send(new OtpMail($this->otp, $this->expiresAt));
            $this->currentChallenge()->update(['delivered_at' => now(), 'delivery_failed_at' => null]);
        } catch (Throwable) {
            // SMTP exceptions may contain the body; never persist/report them.
            throw new RuntimeException('OTP delivery temporarily unavailable.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->currentChallenge()->update(['delivery_failed_at' => now()]);
    }

    private function currentChallenge(): \Illuminate\Database\Query\Builder
    {
        return DB::table('otp_challenges')->where('id', $this->challengeId)->where('generation', $this->generation);
    }
}
