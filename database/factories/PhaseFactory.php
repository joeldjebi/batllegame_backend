<?php

namespace Database\Factories;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'type' => PhaseType::SingleElimination,
            'position' => fn (array $attributes) => Phase::query()
                ->where('competition_id', $attributes['competition_id'])
                ->max('position') + 1,
            'rules' => [],
        ];
    }

    public function groups(int $groupCount = 2, int $qualifiersPerGroup = 2): static
    {
        return $this->state([
            'type' => PhaseType::Groups,
            'qualifiers_per_group' => $qualifiersPerGroup,
            'rules' => ['group_count' => $groupCount, 'allow_draws' => true],
        ]);
    }

    public function doubleElimination(): static
    {
        return $this->state(['type' => PhaseType::DoubleElimination]);
    }

    public function started(): static
    {
        return $this->state(['status' => PhaseStatus::InProgress, 'started_at' => now()]);
    }
}
