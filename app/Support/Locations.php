<?php

namespace App\Support;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;

/**
 * The active places offered in the forms (country > city > commune), cached until
 * the super-admin changes them.
 */
class Locations
{
    private const string KEY = 'locations.tree';

    /**
     * @return list<array{id: int, name: string, flag: ?string, cities: list<array{id: int, name: string, communes: list<array{id: int, name: string}>}>}>
     */
    public static function tree(): array
    {
        return Cache::rememberForever(self::KEY, fn () => Country::query()->active()
            ->with(['cities' => fn ($q) => $q->active()->with(['communes' => fn ($q) => $q->active()])])
            ->get()
            ->filter(fn (Country $country) => $country->cities->isNotEmpty())
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'name' => $country->name,
                'flag' => $country->flag,
                'cities' => $country->cities->map(fn ($city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'communes' => $city->communes->map(fn ($commune) => ['id' => $commune->id, 'name' => $commune->name])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all());
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
