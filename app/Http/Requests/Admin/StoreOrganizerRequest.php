<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrganizerStatus;
use App\Models\Country;
use App\Rules\NationalPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Super-admin creates an organizer and its owner account.
 */
class StoreOrganizerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(OrganizerStatus::class)->only([OrganizerStatus::Pending, OrganizerStatus::Verified])],
            'owner_name' => ['nullable', 'string', 'max:100'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'phone' => ['nullable', 'string', 'max:20', new NationalPhoneNumber($this->country())],
        ];
    }

    public function attributes(): array
    {
        return ['owner_name' => 'nom du propriétaire', 'owner_email' => 'email du propriétaire'];
    }

    public function country(): ?Country
    {
        return $this->filled('country_id') ? Country::query()->active()->find($this->integer('country_id')) : null;
    }
}
