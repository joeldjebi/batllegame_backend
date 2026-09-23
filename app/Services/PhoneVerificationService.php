<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * One-time code sent by SMS to verify a user's phone number.
 */
class PhoneVerificationService
{
    private const int TTL_MINUTES = 10;

    private const int MAX_ATTEMPTS = 5;

    public function __construct(private SmsSender $sms) {}

    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        $this->sms->send($user->phone, "Battle Game : votre code de vérification est {$code}. Il expire dans ".self::TTL_MINUTES.' minutes.');
    }

    /**
     * @throws ValidationException
     */
    public function verify(User $user, string $code): void
    {
        $entry = Cache::get($this->key($user));

        if ($entry === null || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($user));

            throw ValidationException::withMessages(['code' => 'Code expiré. Demandez un nouveau code.']);
        }

        if (! Hash::check($code, $entry['hash'])) {
            Cache::put($this->key($user), [...$entry, 'attempts' => $entry['attempts'] + 1], now()->addMinutes(self::TTL_MINUTES));

            throw ValidationException::withMessages(['code' => 'Code incorrect.']);
        }

        Cache::forget($this->key($user));
        $user->markPhoneAsVerified();
    }

    private function key(User $user): string
    {
        return "phone-verification:{$user->getKey()}";
    }
}
