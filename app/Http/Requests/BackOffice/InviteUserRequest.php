<?php

namespace App\Http\Requests\BackOffice;

use App\Enums\OrganizerRole;
use App\Http\Requests\Concerns\HasPhoneNumber;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Find an existing user by phone number (to add a member or a judge).
 */
class InviteUserRequest extends FormRequest
{
    use HasPhoneNumber;

    private ?User $invitedUser = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->phoneRules(),
            // Only used when adding an organizer member; owners are never invited.
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
                    $validator->errors()->add('phone', "Aucun compte n'existe avec ce numéro.");
                }
            },
        ];
    }

    public function invitedUser(): ?User
    {
        return $this->invitedUser ??= User::query()->where('phone', $this->e164Phone())->first();
    }
}
