<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Security audit trail for OTP sign-in. Never pass codes, raw emails or tokens here:
 * entries carry the event, user id, a keyed email hash, the IP and a timestamp.
 */
class OtpAudit
{
    public static function record(string $event, User|int|string|null $user, ?string $emailHash, ?string $ip): void
    {
        Log::channel('otp')->info($event, [
            'event' => $event,
            'user_id' => $user instanceof User ? $user->id : $user,
            'email_hash' => $emailHash ? substr($emailHash, 0, 16) : null,
            'ip' => $ip,
            'at' => now()->utc()->toIso8601ZuluString(),
        ]);
    }
}
