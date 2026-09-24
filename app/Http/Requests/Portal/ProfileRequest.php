<?php

namespace App\Http\Requests\Portal;

use App\Http\Requests\Concerns\HasLocationInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * « Mon profil » of the member portals and the mobile app: name, email, city, photo.
 */
class ProfileRequest extends FormRequest
{
    use HasLocationInput;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
            ...$this->locationRules(),
        ];
    }

    public function attributes(): array
    {
        return ['photo' => 'photo', ...$this->locationAttributes()];
    }
}
