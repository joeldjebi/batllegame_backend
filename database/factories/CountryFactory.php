<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => "Côte d'Ivoire",
            'iso2' => 'CI',
            'iso3' => 'CIV',
            'dial_code' => '+225',
            'phone_min_length' => 10,
            'phone_max_length' => 10,
            'phone_example' => '0701020304',
            'flag' => '🇨🇮',
            'currency_code' => 'XOF',
            'is_active' => true,
        ];
    }

    /**
     * Reuse the Côte d'Ivoire row when it already exists (iso codes are unique).
     */
    public function ivoryCoast(): Country
    {
        return Country::query()->firstWhere('iso2', 'CI') ?? $this->create();
    }
}
