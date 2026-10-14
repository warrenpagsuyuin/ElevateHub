<?php

namespace App\Providers;

use App\Services\MovementAnalyticsCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql)) {
                app(MovementAnalyticsCache::class)->invalidate($query->connection);
            }
        });
        // Invalidate again after commit: another request may cache pre-commit data
        // between the first write and the moment the transaction becomes visible.
        Event::listen([TransactionCommitted::class, TransactionRolledBack::class], function ($event) {
            app(MovementAnalyticsCache::class)->invalidate($event->connection);
        });
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerOtpRateLimiters();
        RateLimiter::for('analytics-data', fn (Request $request) => Limit::perMinute(60)->by('analytics:'.$request->ip()));
    }

    /**
     * Sign-in limits apply per email AND per IP. Laravel adds Retry-After to 429 responses.
     */
    private function registerOtpRateLimiters(): void
    {
        $emailKey = fn (Request $request): string => hash('sha256', strtolower(trim(
            (string) ($request->input('email') ?: $request->session()->get('login.email', ''))
        )));
        $tooMany = fn (string $text) => function (Request $request, array $headers) use ($text) {
            $seconds = (int) ($headers['Retry-After'] ?? 60);
            $message = sprintf($text, sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60));

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'retry_after' => $seconds], 429, $headers)
                : back()->withErrors(['email' => $message])->withHeaders($headers);
        };

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute((int) config('otp.verify_per_minute_email', 6))->by('otp-verify:e:'.$emailKey($request))
                ->response($tooMany('Too many attempts. Try again in %s or request a new code.')),
            Limit::perMinute((int) config('otp.verify_per_minute_ip', 20))->by('otp-verify:ip:'.$request->ip())
                ->response($tooMany('Too many attempts. Try again in %s or request a new code.')),
        ]);

        $window = max(1, intdiv((int) config('otp.resend_window_seconds', 900), 60));

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinutes($window, (int) config('otp.send_per_window_email', 5))->by('otp-send:e:'.$emailKey($request))
                ->response($tooMany('Too many code requests. Try again in %s.')),
            Limit::perMinutes($window, (int) config('otp.send_per_window_ip', 20))->by('otp-send:ip:'.$request->ip())
                ->response($tooMany('Too many code requests. Try again in %s.')),
        ]);
    }
}
