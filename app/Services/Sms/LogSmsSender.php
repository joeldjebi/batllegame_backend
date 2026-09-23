<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes messages to the log instead of sending them.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $e164Phone, string $message): void
    {
        Log::info('SMS to {phone}: {message}', ['phone' => $e164Phone, 'message' => $message]);
    }
}
