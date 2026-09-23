<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Seed the reference list of countries. Only Côte d'Ivoire is active by default;
     * the others can be activated from the platform back-office.
     */
    public function run(): void
    {
        $countries = [
            // name, iso2, iso3, dial code, min, max, example, flag, currency, active
            ["Côte d'Ivoire", 'CI', 'CIV', '+225', 10, 10, '0701020304', '🇨🇮', 'XOF', true],
            ['Sénégal', 'SN', 'SEN', '+221', 9, 9, '771234567', '🇸🇳', 'XOF', false],
            ['Mali', 'ML', 'MLI', '+223', 8, 8, '65012345', '🇲🇱', 'XOF', false],
            ['Burkina Faso', 'BF', 'BFA', '+226', 8, 8, '70123456', '🇧🇫', 'XOF', false],
            ['Bénin', 'BJ', 'BEN', '+229', 10, 10, '0190123456', '🇧🇯', 'XOF', false],
            ['Togo', 'TG', 'TGO', '+228', 8, 8, '90112345', '🇹🇬', 'XOF', false],
            ['Niger', 'NE', 'NER', '+227', 8, 8, '93123456', '🇳🇪', 'XOF', false],
            ['Guinée', 'GN', 'GIN', '+224', 9, 9, '601123456', '🇬🇳', 'GNF', false],
            ['Cameroun', 'CM', 'CMR', '+237', 9, 9, '671234567', '🇨🇲', 'XAF', false],
            ['Ghana', 'GH', 'GHA', '+233', 9, 9, '231234567', '🇬🇭', 'GHS', false],
            ['France', 'FR', 'FRA', '+33', 9, 9, '612345678', '🇫🇷', 'EUR', false],
        ];

        $now = now();

        DB::table('countries')->upsert(
            array_map(fn (array $c, int $i) => [
                'name' => $c[0],
                'iso2' => $c[1],
                'iso3' => $c[2],
                'dial_code' => $c[3],
                'phone_min_length' => $c[4],
                'phone_max_length' => $c[5],
                'phone_example' => $c[6],
                'flag' => $c[7],
                'currency_code' => $c[8],
                'is_active' => $c[9],
                'position' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ], $countries, array_keys($countries)),
            uniqueBy: ['iso2'],
            // Never overwrite is_active: activation is managed by the platform admin.
            update: ['name', 'iso3', 'dial_code', 'phone_min_length', 'phone_max_length', 'phone_example', 'flag', 'currency_code', 'updated_at'],
        );
    }
}
