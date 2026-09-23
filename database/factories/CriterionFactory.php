<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\Criterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Criterion>
 */
class CriterionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'name' => fake()->randomElement(['Flow', 'Lyrics', 'Présence scénique', 'Technique']),
            'max_points' => 10,
            'weight' => 1,
            'position' => 0,
        ];
    }
}
