<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Email + password login for the web areas (organizer back-office and
 * platform administration). Mobile users log in with their phone instead.
 */
class EmailLoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'email' => Str::lower(trim((string) $this->input('email'))),
            'password' => (string) $this->input('password'),
        ];
    }
}
