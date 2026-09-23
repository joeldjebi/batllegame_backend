<?php

namespace App\Http\Requests\Concerns;

use App\Models\Country;
use App\Rules\NationalPhoneNumber;
use Illuminate\Validation\Rule;

/**
 * For form requests asking for a phone number: an active country (dial code
 * selector) plus the national number, combined into E.164.
 */
trait HasPhoneNumber
{
    private ?Country $phoneCountry = null;

    /**
     * @return array<string, mixed>
     */
    protected function phoneRules(string $countryKey = 'country_id', string $phoneKey = 'phone'): array
    {
        return [
            $countryKey => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            $phoneKey => ['required', 'string', 'max:20', new NationalPhoneNumber($this->phoneCountry($countryKey))],
        ];
    }

    protected function phoneCountry(string $countryKey = 'country_id'): ?Country
    {
        return $this->phoneCountry ??= Country::query()->active()->find($this->integer($countryKey));
    }

    /**
     * Phone number in E.164 format, as stored in users.phone.
     */
    public function e164Phone(string $countryKey = 'country_id', string $phoneKey = 'phone'): string
    {
        return $this->phoneCountry($countryKey)->toE164((string) $this->input($phoneKey));
    }
}
