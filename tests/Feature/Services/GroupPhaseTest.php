<?php

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Jobs\RecalculateGroupStandings;
use App\Services\Competition\RoundRobinScheduler;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

it('schedules a round robin where everyone meets once and nobody plays twice per round', function (int $count) {
    $rounds = RoundRobinScheduler::rounds(range(1, $count));
    $pairs = collect($rounds)->flatten(1)->map(fn ($p) => min($p).'-'.max($p));

    expect($pairs)->toHaveCount($count * ($count - 1) / 2)
        ->and($pairs->unique())->toHaveCount($pairs->count());

    foreach ($rounds as $round) {
        $players = collect($round)->flatten();
        expect($players->unique())->toHaveCount($players->count());
    }
})->with([2, 3, 4, 5, 8]);

it('distributes seeds across groups in a snake and creates the group matches', function () {
    [$competition] = competitionWithParticipants(8);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], qualifiersPerGroup: 2);

    $groups = $phase->groups()->with('participants')->get();

    expect($groups->pluck('name')->all())->toBe(['Poule A', 'Poule B'])
        ->and($groups[0]->participants->pluck('seed')->sort()->values()->all())->toBe([1, 4, 5, 8])
        ->and($groups[1]->participants->pluck('seed')->sort()->values()->all())->toBe([2, 3, 6, 7])
        ->and($phase->matches()->count())->toBe(12)
        ->and($phase->matches()->whereNull('group_id')->count())->toBe(0);
});

it('draws random groups of balanced size', function () {
    [$competition] = competitionWithParticipants(9);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 3], qualifiersPerGroup: 1);

    expect($phase->groups()->withCount('participants')->pluck('participants_count')->all())->toBe([3, 3, 3]);
});

it('queues the standings recalculation when a group match closes', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1], qualifiersPerGroup: 2);
    Queue::fake();

    playMatch($phase->matches()->first(), null);

    Queue::assertPushed(RecalculateGroupStandings::class);
});

it('ranks a group from its closed matches', function () {
    [$competition, $participants] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1, 'draw_method' => 'seed'], qualifiersPerGroup: 2);

    playPhaseByFavorites($phase);

    $standings = $phase->groups()->first()->standings()->with('participant')->get();

    expect($standings->map(fn ($s) => [$s->participant->seed, $s->points, $s->wins, $s->losses, $s->rank])->all())->toBe([
        [1, 9, 3, 0, 1],
        [2, 6, 2, 1, 2],
        [3, 3, 1, 2, 3],
        [4, 0, 0, 3, 4],
    ])->and($standings[0]->score_diff)->toBeGreaterThan(0);
});

it('uses head-to-head to separate participants level on points', function () {
    [$competition, $participants] = competitionWithParticipants(3);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1, 'tie_breakers' => ['confrontation_directe', 'seed']], qualifiersPerGroup: 1);
    [$s1, $s2, $s3] = $participants;

    // Rock-paper-scissors: everyone wins once, then head-to-head is level too, so seed decides.
    playPhaseByFavorites($phase, function ($pair) use ($s1, $s2, $s3) {
        $ids = $pair->pluck('id')->sort()->values()->all();

        return match ($ids) {
            [$s1->id, $s2->id] => $s1,
            [$s2->id, $s3->id] => $s2,
            default => $s3,
        };
    });

    expect($phase->groups()->first()->standings()->with('participant')->get()->pluck('participant.seed')->all())->toBe([1, 2, 3]);
});

it('finishes the group phase, qualifies the top N and crosses groups in the next bracket', function () {
    [$competition, $participants] = competitionWithParticipants(8);
    $groups = startPhase($competition, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], qualifiersPerGroup: 2);

    playPhaseByFavorites($groups);

    expect($groups->fresh()->status)->toBe(PhaseStatus::Finished)
        ->and($participants->map->fresh()->where('status', ParticipantStatus::Eliminated)->pluck('seed')->sort()->values()->all())
        ->toBe([5, 6, 7, 8]);

    // Group A: 1, 4 qualify. Group B: 2, 3 qualify. Bracket seeds: 1A, 1B, 2A, 2B.
    $bracket = startPhase($competition, PhaseType::SingleElimination);
    $semiFinals = $bracket->matches()->where('round', 1)->orderBy('bracket_position')->get();

    expect($semiFinals->map(fn ($m) => seedsOf($m))->all())->toBe([[1, 3], [2, 4]]);
});

it('ignores cancelled matches when deciding that a phase is complete', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1], qualifiersPerGroup: 1);
    $phase->matches()->limit(1)->get()->each->forceFill(['status' => MatchStatus::Cancelled])->each->save();

    playPhaseByFavorites($phase);

    expect($phase->fresh()->status)->toBe(PhaseStatus::Finished);
});
