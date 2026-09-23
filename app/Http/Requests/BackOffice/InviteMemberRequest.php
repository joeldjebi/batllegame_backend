<?php

namespace App\Http\Requests\BackOffice;

use App\Enums\OrganizerRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add an organizer member by email: members log in to the back-office with it.
 */
class InviteMemberRequest extends FormRequest
{
    private ?User $invitedUser = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            // Owners are never invited.
            'role' => ['sometimes', Rule::enum(OrganizerRole::class)->except([OrganizerRole::Owner])],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->invitedUser() === null) {
                    $validator->errors()->add('email', "Aucun compte n'existe avec cet email.");
                }
            },
        ];
    }

    public function invitedUser(): ?User
    {
        return $this->invitedUser ??= User::query()
            ->where('email', Str::lower(trim((string) $this->input('email'))))
            ->first();
    }
}
