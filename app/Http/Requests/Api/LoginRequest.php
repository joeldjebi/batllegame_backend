<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\HasPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phone + password login, shared by the mobile API and the back-office.
 */
class LoginRequest extends FormRequest
{
    use HasPhoneNumber;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->phoneRules(),
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{phone: string, password: string}
     */
    public function credentials(): array
    {
        return ['phone' => $this->e164Phone(), 'password' => (string) $this->input('password')];
    }
}
