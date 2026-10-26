<?php

use App\Mail\OtpMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local or testing preview only.');
}
config(['mail.default' => 'array']);
$sent = Mail::to('preview@example.test')->send(new OtpMail('123456', '2026-10-26T09:32:04Z'));
$message = $sent->getSymfonySentMessage()->getOriginalMessage();
$directory = storage_path('framework/testing/otp-mail');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$html = preg_replace('/src="cid:[^"]+"/', 'src="http://email-preview.test/images/email/elevate-logo.png"', $message->getHtmlBody());
file_put_contents($directory.'/preview.html', $html);
file_put_contents($directory.'/preview.eml', $message->toString());
echo 'Synthetic HTML and multipart EML written to '.$directory."; array transport only.\n";
