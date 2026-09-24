<?php

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Jobs\RecalculateGroupStandings;
use App\Models\BattleMatch;
use App\Models\Participant;
use App\Models\PublicVote;
use App\Models\User;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\MatchCloser;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

/**
 * Open the vote of a group and give each artist (by seed) the number of public votes listed.
 *
 * @param  array<int, int>  $votesBySeed
 */
function voteInGroup(BattleMatch $match, array $votesBySeed): void
{
    $match->refresh()->forceFill(['status' => MatchStatus::Voting])->save();

    foreach ($match->participants()->get() as $participant) {
        for ($i = 0; $i < ($votesBySeed[$participant->seed] ?? 0); $i++) {
            $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $participant->id]);
            $vote->user_id = User::factory()->create()->id;
            $vote->save();
        }
    }
}

it('puts every artist of a group in one match: nobody faces anybody', function () {
    [$competition] = competitionWithParticipants(8);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], qualifiersPerGroup: 2);

    $groups = $phase->groups()->with('participants')->get();
    $matches = $phase->matches()->withCount('slots')->orderBy('bracket_position')->get();

    expect($groups->pluck('name')->all())->toBe(['Poule A', 'Poule B'])
        ->and($groups[0]->participants->pluck('seed')->sort()->values()->all())->toBe([1, 4, 5, 8])
        ->and($groups[1]->participants->pluck('seed')->sort()->values()->all())->toBe([2, 3, 6, 7])
        ->and($matches)->toHaveCount(2)
        ->and($matches->pluck('slots_count')->all())->toBe([4, 4])
        ->and($matches->pluck('group_id')->all())->toBe($groups->pluck('id')->all())
        ->and($matches[0]->title())->toBe('Poule A');
});

it('draws random groups of balanced size', function () {
    [$competition] = competitionWithParticipants(10);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 3], qualifiersPerGroup: 1);

    expect($phase->groups()->withCount('participants')->pluck('participants_count')->sort()->values()->all())->toBe([3, 3, 4]);
});

it('ranks a group by its scores, the jury and seeds breaking ties', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1], qualifiersPerGroup: 2);
    $match = $phase->matches()->first();

    // Seed 3 first, then seeds 1 and 4 level on votes (seed 1 wins the tie), seed 2 last.
    voteInGroup($match, [3 => 5, 1 => 2, 4 => 2, 2 => 1]);
    app(MatchCloser::class)->close($match);

    $ranks = $match->slots()->with('participant')->get()->mapWithKeys(fn ($slot) => [$slot->participant->seed => $slot->rank])->sortKeys()->all();

    expect($match->fresh()->status)->toBe(MatchStatus::Closed)
        ->and($match->fresh()->winner_id)->toBeNull()
        ->and($ranks)->toBe([1 => 2, 2 => 4, 3 => 1, 4 => 3])
        ->and($phase->groups()->first()->standings()->pluck('rank')->all())->toBe([1, 2, 3, 4]);
});

it('queues the ranking copy when a group closes', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1], qualifiersPerGroup: 2);
    Queue::fake();

    playMatch($phase->matches()->first(), null);

    Queue::assertPushed(RecalculateGroupStandings::class);
});

it('publishes the group results and seeds the next bracket with the qualifiers', function () {
    [$competition] = competitionWithParticipants(8);
    $groups = startPhase($competition, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], qualifiersPerGroup: 2);

    playPhaseByFavorites($groups);

    expect($groups->fresh()->status)->toBe(PhaseStatus::InProgress);

    app(GroupResultsService::class)->publish($groups);

    expect($groups->fresh()->status)->toBe(PhaseStatus::Finished)
        ->and($groups->fresh()->results_published_at)->not->toBeNull()
        ->and(Participant::query()->where('status', ParticipantStatus::Eliminated)->pluck('seed')->sort()->values()->all())->toBe([5, 6, 7, 8]);

    // 1A vs 2B and 1B vs 2A.
    $bracket = startPhase($competition, PhaseType::SingleElimination);
    $firstRound = $bracket->matches()->where('round', 1)->orderBy('bracket_position')->get()->map(fn ($m) => collect(seedsOf($m))->sort()->values()->all())->all();

    expect($firstRound)->toBe([[1, 3], [2, 4]]);
});

it('refuses to publish while a group is still open, and twice', function () {
    [$competition] = competitionWithParticipants(6);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 2], qualifiersPerGroup: 1);
    playMatch($phase->matches()->first(), null);

    expect(fn () => app(GroupResultsService::class)->publish($phase))->toThrow(CompetitionFlowException::class, '1 poule(s) pas encore close(s)');

    playMatch($phase->matches()->where('status', '!=', MatchStatus::Closed)->first(), null);
    app(GroupResultsService::class)->publish($phase);

    expect(fn () => app(GroupResultsService::class)->publish($phase))->toThrow(CompetitionFlowException::class, 'déjà publiés');
});

it('allows one public vote for the whole phase, and none from its artists', function () {
    [$competition, $participants] = competitionWithParticipants(6);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 2], qualifiersPerGroup: 1);
    [$a, $b] = $phase->matches()->orderBy('bracket_position')->get()->each(fn ($m) => $m->forceFill(['status' => MatchStatus::Voting])->save())->all();
    $url = fn (BattleMatch $match) => "/api/competitions/{$competition->slug}/matches/{$match->id}/votes";
    $artistOf = fn (BattleMatch $match) => $match->participants()->first();
    $fan = User::factory()->create();

    $this->actingAs($fan)->postJson($url($a), ['participant_id' => $artistOf($a)->id])->assertCreated();
    $this->actingAs($fan)->postJson($url($b), ['participant_id' => $artistOf($b)->id])->assertStatus(409)->assertJsonPath('message', 'Vous avez déjà voté pour cette phase : un seul vote par phase.');

    // An artist of group A cannot vote in group B either.
    $this->actingAs($artistOf($a)->user)->postJson($url($b), ['participant_id' => $artistOf($b)->id])->assertForbidden();

    expect(PublicVote::query()->count())->toBe(1);
});

it('keeps the ranking private until the organizer publishes it', function () {
    [$competition] = competitionWithParticipants(4);
    $phase = startPhase($competition, PhaseType::Groups, ['group_count' => 1], qualifiersPerGroup: 2);
    $match = $phase->matches()->first();
    playMatch($match, null);
    $url = "/api/competitions/{$competition->slug}/matches/{$match->id}";

    $this->getJson($url)->assertOk()->assertJsonPath('data.is_group', true)->assertJsonPath('data.slots.0.final_score', null)->assertJsonPath('data.slots.0.rank', null);

    app(GroupResultsService::class)->publish($phase);

    expect($this->getJson($url)->json('data.slots.0.rank'))->not->toBeNull();
});
