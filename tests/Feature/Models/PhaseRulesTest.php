<?php

use App\Data\PhaseRules;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Enums\TieBreaker;
use App\Enums\VoteMode;
use App\Exceptions\PhaseRulesFrozenException;
use App\Models\Phase;
use Illuminate\Validation\ValidationException;

it('builds default rules from an empty array', function () {
    $rules = PhaseRules::fromArray([]);

    expect($rules->rounds)->toBe(1)
        ->and($rules->voteMode)->toBe(VoteMode::Mixed)
        ->and($rules->juryWeight)->toBe(50)
        ->and($rules->publicWeight)->toBe(50)
        ->and($rules->tieBreakers)->toBe([TieBreaker::JuryScore, TieBreaker::PublicScore, TieBreaker::Seed]);
});

it('normalizes weights for single-source vote modes', function () {
    $rules = PhaseRules::fromArray(['vote_mode' => 'jury', 'jury_weight' => 30, 'public_weight' => 70]);

    expect($rules->juryWeight)->toBe(100)->and($rules->publicWeight)->toBe(0);
});

it('rejects mixed weights that do not add up to 100', function () {
    PhaseRules::fromArray(['vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 60]);
})->throws(ValidationException::class);

it('rejects unknown tie breakers', function () {
    PhaseRules::fromArray(['tie_breakers' => ['coin_flip']]);
})->throws(ValidationException::class);

it('round-trips through toArray', function () {
    $rules = PhaseRules::fromArray(['rounds' => 3, 'jury_weight' => 70, 'public_weight' => 30, 'group_count' => 4]);

    expect(PhaseRules::fromArray($rules->toArray()))->toEqual($rules);
});

it('casts the rules column to a PhaseRules object', function () {
    $phase = Phase::factory()->create(['rules' => ['rounds' => 2]]);

    expect($phase->fresh()->rules)
        ->toBeInstanceOf(PhaseRules::class)
        ->rounds->toBe(2);
});

it('requires a group count for group phases', function () {
    Phase::factory()->create(['type' => PhaseType::Groups, 'rules' => []]);
})->throws(ValidationException::class);

it('forbids draws outside group phases', function () {
    Phase::factory()->create(['rules' => ['allow_draws' => true]]);
})->throws(ValidationException::class);

it('allows editing rules while the phase is pending', function () {
    $phase = Phase::factory()->create();

    $phase->update(['rules' => $phase->rules->with(['rounds' => 3])]);

    expect($phase->fresh()->rules->rounds)->toBe(3);
});

it('freezes rules once the phase has started', function () {
    $phase = Phase::factory()->create();
    $phase->markAsStarted();

    $phase->update(['rules' => $phase->rules->with(['rounds' => 3])]);
})->throws(PhaseRulesFrozenException::class);

it('still allows status changes on a started phase', function () {
    $phase = Phase::factory()->started()->create();

    $phase->forceFill(['status' => PhaseStatus::Finished])->save();

    expect($phase->fresh()->status)->toBe(PhaseStatus::Finished);
});
