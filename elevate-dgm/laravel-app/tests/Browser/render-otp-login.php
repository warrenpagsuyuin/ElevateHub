<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{URL, View, Vite};
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local or testing preview only.');
}
$directory = storage_path('framework/testing/otp-login');
if (! is_dir($directory)) mkdir($directory, 0700, true);
Vite::useHotFile($directory.'/unused.hot');
URL::forceRootUrl('http://127.0.0.1:8000');
View::share('errors', new ViewErrorBag);
$base = now('UTC');
$state = [
    'server_now' => $base->toIso8601String(),
    'expires_at' => $base->copy()->addMinutes(5)->toIso8601String(),
    'resend_available_at' => $base->copy()->addSeconds(60)->toIso8601String(),
    'attempts_left' => 5, 'locked_until' => null, 'delivery_failed' => false,
    'masked_email' => 'w*****0@example.com', 'resends_left' => 3,
];
file_put_contents($directory.'/otp.html', view('auth.login', ['step' => 'otp', 'otpState' => $state])->render());
file_put_contents($directory.'/email.html', view('auth.login', ['step' => 'email'])->render());
file_put_contents($directory.'/password.html', view('auth.login', ['step' => 'password'])->render());
echo "Rendered synthetic OTP, email, and admin previews; no account lookup or email delivery.\n";
