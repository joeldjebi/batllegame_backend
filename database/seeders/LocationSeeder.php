<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Optional starter list (Côte d'Ivoire): the super-admin creates places from the console.
 * php artisan db:seed --class=LocationSeeder. Only adds what is missing.
 */
class LocationSeeder extends Seeder
{
    /** @var array<string, list<string>> city => communes */
    public const array CI = [
        'Abidjan' => ['Abobo', 'Adjamé', 'Anyama', 'Attécoubé', 'Bingerville', 'Cocody', 'Koumassi', 'Marcory', 'Plateau', 'Port-Bouët', 'Songon', 'Treichville', 'Yopougon'],
        'Yamoussoukro' => [], 'Bouaké' => [], 'Daloa' => [], 'San-Pédro' => [], 'Korhogo' => [], 'Man' => [],
        'Gagnoa' => [], 'Divo' => [], 'Abengourou' => [], 'Soubré' => [], 'Grand-Bassam' => [], 'Bondoukou' => [],
        'Séguéla' => [], 'Odienné' => [], 'Agboville' => [], 'Dabou' => [], 'Sassandra' => [], 'Bouaflé' => [],
        'Duékoué' => [], 'Ferkessédougou' => [], 'Guiglo' => [], 'Katiola' => [], 'Issia' => [], 'Sinfra' => [],
        'Adzopé' => [], 'Aboisso' => [], 'Dimbokro' => [], 'Toumodi' => [], 'Tiassalé' => [], 'Jacqueville' => [],
    ];

    public function run(): void
    {
        $country = Country::query()->where('iso2', 'CI')->first();

        if ($country === null) {
            return;
        }

        $position = 0;

        foreach (self::CI as $name => $communes) {
            $city = City::query()->firstOrCreate(['country_id' => $country->id, 'name' => $name], ['position' => $position++]);

            foreach ($communes as $i => $commune) {
                $city->communes()->firstOrCreate(['name' => $commune], ['position' => $i]);
            }
        }
    }
}
