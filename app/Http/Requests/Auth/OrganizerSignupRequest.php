<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasLocationInput;
use App\Http\Requests\Concerns\HasPhoneNumber;
use App\Support\Locations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Self sign-up of an organizer: the organizer and, for a visitor, its owner account.
 */
class OrganizerSignupRequest extends FormRequest
{
    use HasLocationInput, HasPhoneNumber;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizer = [
            'organizer_name' => ['required', 'string', 'min:2', 'max:255'],
            ...$this->locationRules(required: Locations::tree() !== []),
            'description' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ];

        // Already signed in: the organizer only.
        if ($this->user('web')) {
            return $organizer;
        }

        return [
            ...$organizer,
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            ...$this->phoneRules(),
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return ['organizer_name' => "nom de l'organisateur", 'name' => 'nom complet', 'terms' => 'conditions', ...$this->locationAttributes()];
    }

    public function messages(): array
    {
        return ['terms.accepted' => 'Acceptez les conditions pour créer votre espace.'];
    }
}
