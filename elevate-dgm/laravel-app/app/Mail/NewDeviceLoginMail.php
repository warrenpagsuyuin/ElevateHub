<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class NewDeviceLoginMail extends Mailable
{
    use Queueable;

    public function __construct(public string $device, public string $ip, public string $when)
    {
    }

    public function build(): self
    {
        return $this->subject('New sign-in to your Elevate account')
            ->view('emails.new-device')
            ->text('emails.new-device-text');
    }
}
