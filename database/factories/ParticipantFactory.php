<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'user_id' => User::factory(),
            'stage_name' => 'MC '.fake()->unique()->firstName(),
            'seed' => null,
            'status' => ParticipantStatus::Validated,
        ];
    }
}
