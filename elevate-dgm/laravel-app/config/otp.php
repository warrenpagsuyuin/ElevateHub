<?php

$production = env('APP_ENV') === 'production';

return [
    // Single source of truth for code lifetime: backend expiry, UI timer and email copy.
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'max_resends' => (int) env('OTP_MAX_RESENDS', 3),
    'resend_window_seconds' => (int) env('OTP_RESEND_WINDOW_SECONDS', 900),
    'lockout_seconds' => (int) env('OTP_LOCKOUT_SECONDS', 300),

    // Per-email / per-IP request limits (per minute for verify, per window for send).
    'verify_per_minute_email' => (int) env('OTP_VERIFY_PER_MINUTE_EMAIL', 6),
    'verify_per_minute_ip' => (int) env('OTP_VERIFY_PER_MINUTE_IP', 20),
    'send_per_window_email' => (int) env('OTP_SEND_PER_WINDOW_EMAIL', 5),
    'send_per_window_ip' => (int) env('OTP_SEND_PER_WINDOW_IP', 20),

    'pepper' => env('OTP_PEPPER', env('APP_KEY')),

    // Production must use a real queue + worker (enforced in SendOtpMail). Locally the
    // default is "sync" so codes arrive with plain `php artisan serve` and no worker.
    'queue_connection' => env('OTP_QUEUE_CONNECTION', $production ? 'database' : 'sync'),
    'queue' => env('OTP_QUEUE', 'otp'),
    'mailer' => env('OTP_MAILER', env('MAIL_MAILER', 'smtp')),

    // Lets the "log" mailer receive OTP mail in APP_ENV=local only. Keep false elsewhere.
    'allow_local_log' => (bool) env('OTP_ALLOW_LOCAL_LOG', false),

    // Must be HTTPS to be used in email; otherwise the "ELEVATE" wordmark is shown.
    'logo_url' => env('OTP_LOGO_URL', rtrim((string) env('APP_URL', ''), '/').'/assets/images/elevate-logo-login.png'),

    'new_device_alerts' => (bool) env('OTP_NEW_DEVICE_ALERTS', true),
];
