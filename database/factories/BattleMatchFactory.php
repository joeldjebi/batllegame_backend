<?php

namespace Database\Factories;

use App\Models\BattleMatch;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * competition_id is derived from the phase by the model itself.
 *
 * @extends Factory<BattleMatch>
 */
class BattleMatchFactory extends Factory
{
    protected $model = BattleMatch::class;

    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'round' => 1,
            'bracket_position' => 1,
        ];
    }
}
