<?php

use App\Enums\BracketSide;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Exceptions\PhaseRulesFrozenException;
use App\Services\Competition\SeedOrder;

require_once __DIR__.'/helpers.php';

it('orders seeds so that the top seeds meet as late as possible', function () {
    expect(SeedOrder::positions(2))->toBe([1, 2])
        ->and(SeedOrder::positions(4))->toBe([1, 4, 2, 3])
        ->and(SeedOrder::positions(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6])
        ->and(SeedOrder::bracketSize(5))->toBe(8)
        ->and(SeedOrder::bracketSize(8))->toBe(8);
});

it('generates a complete single elimination bracket for 8 participants', function () {
    [$competition] = competitionWithParticipants(8);
    $phase = startPhase($competition, PhaseType::SingleElimination);

    $matches = $phase->matches()->get();
    $firstRound = $matches->where('round', 1)->sortBy('bracket_position')->values();
    $final = $matches->firstWhere('round', 3);

    expect($matches)->toHaveCount(7)
        ->and($matches->where('round', 2))->toHaveCount(2)
        ->and($firstRound->map(fn ($m) => seedsOf($m))->all())->toBe([[1, 8], [4, 5], [2, 7], [3, 6]])
        ->and($final->next_match_id)->toBeNull()
        ->and($firstRound[0]->next_match_id)->toBe($firstRound[1]->next_match_id)
        ->and([$firstRound[0]->next_match_slot, $firstRound[1]->next_match_slot])->toBe([1, 2])
        ->and($matches->whereNotNull('loser_next_match_id'))->toBeEmpty()
        ->and($phase->status)->toBe(PhaseStatus::InProgress)
        ->and($competition->fresh()->status)->toBe(CompetitionStatus::InProgress);
});

it('gives byes to the best seeds and advances them immediately', function () {
    [$competition] = competitionWithParticipants(6);
    $phase = startPhase($competition, PhaseType::SingleElimination);

    $firstRound = $phase->matches()->where('round', 1)->orderBy('bracket_position')->get();
    $walkovers = $firstRound->where('status', MatchStatus::Closed);
    $semiFinals = $phase->matches()->where('round', 2)->orderBy('bracket_position')->get();

    expect($firstRound->map(fn ($m) => seedsOf($m))->all())->toBe([[1, null], [4, 5], [2, null], [3, 6]])
        ->and($walkovers)->toHaveCount(2)
        ->and(seedsOf($semiFinals[0]))->toBe([1, null])
        ->and(seedsOf($semiFinals[1]))->toBe([2, null]);
});

it('plays a single elimination bracket to the end', function () {
    [$competition, $participants] = competitionWithParticipants(5);
    $phase = startPhase($competition, PhaseType::SingleElimination);

    playPhaseByFavorites($phase);

    $final = $phase->matches()->where('round', 3)->first();

    expect($final->winner->seed)->toBe(1)
        ->and($phase->fresh()->status)->toBe(PhaseStatus::Finished)
        ->and($competition->fresh()->status)->toBe(CompetitionStatus::Finished)
        ->and($participants->map->fresh()->where('status', ParticipantStatus::Eliminated))->toHaveCount(4);
});

it('generates a double elimination bracket with losers and grand final links', function () {
    [$competition] = competitionWithParticipants(8);
    $phase = startPhase($competition, PhaseType::DoubleElimination);

    $matches = $phase->matches()->get();
    $winners = $matches->where('bracket', BracketSide::Winners);
    $losers = $matches->where('bracket', BracketSide::Losers);
    $grandFinal = $matches->firstWhere('bracket', BracketSide::GrandFinal);
    $winnersFinal = $winners->firstWhere('round', 3);
    $losersFinal = $losers->firstWhere('round', 4);

    expect($winners)->toHaveCount(7)
        ->and($losers)->toHaveCount(6)
        ->and($losers->groupBy('round')->map->count()->all())->toBe([1 => 2, 2 => 2, 3 => 1, 4 => 1])
        ->and($winners->whereNull('loser_next_match_id'))->toBeEmpty()
        ->and([$winnersFinal->next_match_id, $winnersFinal->next_match_slot])->toBe([$grandFinal->id, 1])
        ->and([$winnersFinal->loser_next_match_id, $winnersFinal->loser_next_match_slot])->toBe([$losersFinal->id, 2])
        ->and([$losersFinal->next_match_id, $losersFinal->next_match_slot])->toBe([$grandFinal->id, 2])
        ->and($grandFinal->next_match_id)->toBeNull();
});

it('plays a double elimination bracket: a participant is out after two losses', function () {
    [$competition, $participants] = competitionWithParticipants(8);
    $phase = startPhase($competition, PhaseType::DoubleElimination);

    playPhaseByFavorites($phase);

    $grandFinal = $phase->matches()->where('bracket', BracketSide::GrandFinal)->first();

    expect(seedsOf($grandFinal))->toBe([1, 2])
        ->and($grandFinal->winner->seed)->toBe(1)
        ->and($phase->fresh()->status)->toBe(PhaseStatus::Finished)
        ->and($participants->map->fresh()->where('status', ParticipantStatus::Eliminated))->toHaveCount(7);
});

it('handles byes in double elimination', function () {
    [$competition] = competitionWithParticipants(5);
    $phase = startPhase($competition, PhaseType::DoubleElimination);

    playPhaseByFavorites($phase);

    $grandFinal = $phase->matches()->where('bracket', BracketSide::GrandFinal)->first();

    expect($grandFinal->winner->seed)->toBe(1)
        ->and($phase->fresh()->status)->toBe(PhaseStatus::Finished)
        ->and($phase->matches()->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count())->toBe(0);
});

it('plays the grand final reset only when the losers bracket champion wins the first final', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::DoubleElimination, ['grand_final_reset' => true]);

    // Favorites win everywhere except the first grand final, won by the losers bracket champion.
    playPhaseByFavorites($phase, function ($pair, $match) {
        return $match->bracket === BracketSide::GrandFinal && $match->round === 1
            ? $pair->sortByDesc('seed')->first()
            : $pair->sortBy('seed')->first();
    });

    $reset = $phase->matches()->where('bracket', BracketSide::GrandFinal)->where('round', 2)->first();

    expect($reset->status)->toBe(MatchStatus::Closed)
        ->and($reset->winner->seed)->toBe(1);
});

it('cancels the reset when the winners bracket champion wins the first final', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::DoubleElimination, ['grand_final_reset' => true]);

    playPhaseByFavorites($phase);

    $reset = $phase->matches()->where('bracket', BracketSide::GrandFinal)->where('round', 2)->first();

    expect($reset->status)->toBe(MatchStatus::Cancelled)
        ->and($phase->fresh()->status)->toBe(PhaseStatus::Finished);
});

it('freezes the rules of a started phase', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::SingleElimination);

    $phase->update(['rules' => $phase->rules->with(['rounds' => 5])]);
})->throws(PhaseRulesFrozenException::class);

it('refuses to start a phase twice or with too few participants', function () {
    [$competition] = competitionWithParticipants(1);

    startPhase($competition, PhaseType::SingleElimination);
})->throws(CompetitionFlowException::class);

it('refuses to start a phase before the previous one is finished', function () {
    [$competition] = competitionWithParticipants(8);
    startPhase($competition, PhaseType::Groups, ['group_count' => 2], qualifiersPerGroup: 2);

    startPhase($competition, PhaseType::SingleElimination);
})->throws(CompetitionFlowException::class);
