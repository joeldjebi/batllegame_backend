<?php

namespace App\Services\Sms;

/**
 * Sends text messages. Bind a real provider implementation in production.
 */
interface SmsSender
{
    public function send(string $e164Phone, string $message): void;
}
