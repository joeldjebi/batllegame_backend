<?php

namespace Database\Factories;

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Models\Competition;
use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Battle '.fake()->unique()->words(2, true);

        return [
            'organizer_id' => Organizer::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'discipline' => Discipline::Rap,
            'mode' => CompetitionMode::OnSite,
            'status' => CompetitionStatus::Registration,
            'registration_ends_at' => now()->addWeek(),
            'max_participants' => 32,
            'entry_fee' => 0,
            'currency' => 'XOF',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => CompetitionStatus::Draft]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => CompetitionStatus::InProgress]);
    }
}
