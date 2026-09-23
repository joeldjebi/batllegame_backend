<?php

namespace App\Http\Requests\BackOffice;

use App\Enums\OrganizerRole;
use App\Models\Country;
use App\Rules\NationalPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add an organizer member (manager): an existing back-office account by email,
 * or a new account (name + phone) created on the fly.
 */
class InviteMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'phone' => ['nullable', 'string', 'max:20', new NationalPhoneNumber($this->country())],
            // Owners are never invited.
            'role' => ['required', Rule::enum(OrganizerRole::class)->except([OrganizerRole::Owner])],
        ];
    }

    public function country(): ?Country
    {
        return $this->filled('country_id') ? Country::query()->active()->find($this->integer('country_id')) : null;
    }
}
