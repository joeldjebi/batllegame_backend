<?php

namespace Database\Factories;

use App\Enums\JudgeStatus;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Judge>
 */
class JudgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'user_id' => User::factory(),
            'status' => JudgeStatus::Accepted,
        ];
    }
}
