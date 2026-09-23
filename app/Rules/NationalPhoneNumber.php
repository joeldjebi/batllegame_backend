<?php

namespace App\Rules;

use App\Models\Country;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a national phone number against the length rules of the selected country.
 */
class NationalPhoneNumber implements ValidationRule
{
    public function __construct(private ?Country $country) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->country === null) {
            return; // country_id validation reports the error.
        }

        if (! is_string($value) || ! $this->country->isValidNationalNumber($value)) {
            $fail(sprintf(
                'Le numéro doit comporter %s chiffres pour %s (ex. %s).',
                $this->country->phone_min_length === $this->country->phone_max_length
                    ? $this->country->phone_min_length
                    : "{$this->country->phone_min_length} à {$this->country->phone_max_length}",
                $this->country->name,
                $this->country->phone_example,
            ));
        }
    }
}
