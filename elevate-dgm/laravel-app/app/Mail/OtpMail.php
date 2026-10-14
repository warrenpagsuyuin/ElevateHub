<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;

class OtpMail extends Mailable
{
    public string $expiresAt;

    public function __construct(public string $otp, ?string $expiresAt = null)
    {
        $this->expiresAt = Carbon::parse($expiresAt ?? now()->addSeconds((int) config('otp.ttl_seconds', 300))->toIso8601String())->utc()->format('d M Y, H:i:s').' UTC';
    }

    public function build(): self
    {
        $seconds = max(1, (int) config('otp.ttl_seconds', 300));
        $lifetime = $seconds % 60 === 0 ? ($seconds / 60).' '.($seconds === 60 ? 'minute' : 'minutes') : $seconds.' seconds';
        $logo = (string) config('otp.logo_url', '');

        return $this->subject('Your Elevate sign-in code')->view('emails.otp')->text('emails.otp-text')->with([
            'lifetime' => $lifetime,
            'logoUrl' => filter_var($logo, FILTER_VALIDATE_URL) && strtolower((string) parse_url($logo, PHP_URL_SCHEME)) === 'https' ? $logo : null,
        ]);
    }
}
