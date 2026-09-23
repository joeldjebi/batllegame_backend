<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory()->groups(),
            'name' => 'Poule '.fake()->unique()->randomLetter(),
        ];
    }
}
