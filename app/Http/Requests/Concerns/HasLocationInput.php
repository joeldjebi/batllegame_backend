<?php

namespace App\Http\Requests\Concerns;

use App\Models\Commune;
use Illuminate\Validation\Rule;

/**
 * city_id / commune_id picked from the super-admin's lists (x-location-select).
 * When the city has communes, a required location also needs its commune.
 */
trait HasLocationInput
{
    /**
     * @return array<string, mixed>
     */
    protected function locationRules(bool $required = false): array
    {
        $cityHasCommunes = fn () => $this->filled('city_id') && Commune::query()->where('city_id', $this->integer('city_id'))->where('is_active', true)->exists();

        return [
            'city_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('cities', 'id')->where('is_active', true)
                ->whereIn('country_id', fn ($q) => $q->select('id')->from('countries')->where('is_active', true))],
            'commune_id' => [Rule::requiredIf(fn () => $required && $cityHasCommunes()), 'nullable', 'integer',
                Rule::exists('communes', 'id')->where('city_id', $this->integer('city_id'))->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function locationAttributes(): array
    {
        return ['city_id' => 'ville', 'commune_id' => 'commune'];
    }
}
