<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Models\BattleMatch;
use App\Models\User;
use App\Services\Competition\StageService;
use App\Services\VotingService;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
});

/** Opens the public vote of a match until [minutes] from now. */
function openVote(BattleMatch $match, int $minutes): BattleMatch
{
    $match->forceFill(['status' => MatchStatus::Voting, 'voting_opens_at' => now()->subMinute(), 'voting_closes_at' => now()->addMinutes($minutes)])->save();

    return $match->fresh();
}

it('lists the duels whose vote is open, soonest to close first, with the artists', function () {
    ['competition' => $competition, 'phase' => $phase] = startedCompetition(CompetitionMode::Online, 4);
    [$first, $second] = $phase->matches()->where('round', 1)->orderBy('bracket_position')->get()->all();
    openVote($first, 120);
    openVote($second, 30);

    // Not public: a draft competition's match.
    ['competition' => $draft, 'phase' => $draftPhase] = startedCompetition(CompetitionMode::Online, 2);
    openVote($draftPhase->matches()->first(), 10);
    $draft->update(['status' => CompetitionStatus::Draft]);

    $response = $this->getJson('/api/live')->assertOk();

    expect($response->json('data.*.id'))->toBe([$second->id, $first->id])
        ->and($response->json('data.0.is_group'))->toBeFalse()
        ->and($response->json('data.0.artists'))->toHaveCount(2)
        ->and($response->json('data.0.artists.0'))->toHaveKeys(['participant_id', 'stage_name', 'avatar_url', 'media'])
        ->and($response->json('data.0.competition.slug'))->toBe($competition->slug)
        ->and($response->json('data.0.my_vote'))->toBeNull();
});

it('tells the viewer their vote and whether the match is theirs', function () {
    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online, 2);
    $match = openVote($phase->matches()->first(), 60);
    $fan = User::factory()->create(['phone_verified_at' => now()]);
    $artistId = $match->slots()->first()->participant_id;
    app(VotingService::class)->cast($fan, $match, $artistId);

    $this->actingAs($fan, 'sanctum')->getJson('/api/live')->assertOk()
        ->assertJsonPath('data.0.my_vote', $artistId)
        ->assertJsonPath('data.0.is_mine', false);

    $this->actingAs($participants[0]->user, 'sanctum')->getJson('/api/live')->assertJsonPath('data.0.is_mine', true);
});

it('shows nothing when no vote is open', function () {
    startedCompetition(CompetitionMode::Online, 2);

    $this->getJson('/api/live')->assertOk()->assertJsonCount(0, 'data');
});

it('lists the battles coming next with the time their vote opens', function () {
    ['competition' => $competition, 'phase' => $phase] = startedCompetition(CompetitionMode::Online, 4);
    $stage = $phase->stages()->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addMinutes(20), 'voting_closes_at' => now()->addDays(2)]);
    app(StageService::class)->openSubmissions($stage);

    // Jury only: the public never votes, not listed.
    ['phase' => $juryPhase] = startedCompetition(CompetitionMode::Online, 2, rules: ['vote_mode' => 'jury']);
    $juryStage = $juryPhase->stages()->first();
    app(StageService::class)->schedule($juryStage, ['submission_deadline' => now()->addMinutes(5), 'voting_closes_at' => now()->addDay()]);
    app(StageService::class)->openSubmissions($juryStage);

    $response = $this->getJson('/api/live')->assertOk()->assertJsonCount(0, 'data');

    expect($response->json('upcoming'))->toHaveCount(2)
        ->and($response->json('upcoming.0.competition.slug'))->toBe($competition->slug)
        ->and($response->json('upcoming.0.submissions_open'))->toBeTrue()
        ->and($response->json('upcoming.0.artists'))->toHaveCount(2)
        ->and(Carbon\Carbon::parse($response->json('upcoming.0.voting_opens_at'))->diffInMinutes(now(), true))->toBeLessThan(21);

    // Deadline moved after the planned opening: the vote opens with the deadline.
    $stage->forceFill(['voting_opens_at' => now()->subMinutes(10)])->save();
    $opensAt = $this->getJson('/api/live')->assertJsonCount(2, 'upcoming')->json('upcoming.0.voting_opens_at');
    expect(Carbon\Carbon::parse($opensAt)->timestamp)->toBe($stage->submission_deadline->timestamp);

    // Deadline passed: no longer upcoming.
    $this->travel(21)->minutes();
    app(StageService::class)->processDue();
    $this->getJson('/api/live')->assertJsonCount(0, 'upcoming');
});
