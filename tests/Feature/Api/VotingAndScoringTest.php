<?php

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->competition = Competition::factory()->inProgress()->create();
    $this->phase = Phase::factory()->for($this->competition)->started()->create();
    [$this->a, $this->b] = Participant::factory()->for($this->competition)->count(2)->create();

    $this->match = BattleMatch::factory()->create(['phase_id' => $this->phase->id]);
    $this->match->forceFill(['status' => MatchStatus::Voting])->save();
    $this->match->participants()->attach($this->a->id, ['slot' => 1]);
    $this->match->participants()->attach($this->b->id, ['slot' => 2]);

    $this->voteUrl = "/api/competitions/{$this->competition->slug}/matches/{$this->match->id}/votes";
});

it('lets a verified user vote only once per match', function () {
    $voter = User::factory()->create();

    $this->actingAs($voter)->postJson($this->voteUrl, ['participant_id' => $this->a->id])->assertCreated();
    $this->actingAs($voter)->postJson($this->voteUrl, ['participant_id' => $this->b->id])->assertStatus(409);

    expect($this->match->publicVotes()->count())->toBe(1);
});

it('refuses votes from unverified phones', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson($this->voteUrl, ['participant_id' => $this->a->id])
        ->assertForbidden();
});

it('refuses votes from a participant of the match', function () {
    $this->actingAs($this->a->user)
        ->postJson($this->voteUrl, ['participant_id' => $this->a->id])
        ->assertForbidden();
});

it('only accepts a participant of this match', function () {
    $outsider = Participant::factory()->for($this->competition)->create();

    $this->actingAs(User::factory()->create())
        ->postJson($this->voteUrl, ['participant_id' => $outsider->id])
        ->assertJsonValidationErrors('participant_id');
});

it('refuses votes when voting is closed', function () {
    $this->match->forceFill(['status' => MatchStatus::Closed])->save();

    $this->actingAs(User::factory()->create())
        ->postJson($this->voteUrl, ['participant_id' => $this->a->id])
        ->assertForbidden();
});

it('does not resolve a match through another competition slug', function () {
    $other = Competition::factory()->inProgress()->create();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/competitions/{$other->slug}/matches/{$this->match->id}/votes", ['participant_id' => $this->a->id])
        ->assertNotFound();
});

it('hides draft competitions from the public API', function () {
    $draft = Competition::factory()->draft()->create();

    $this->getJson("/api/competitions/{$draft->slug}")->assertNotFound();
    $this->getJson('/api/competitions')->assertJsonMissing(['slug' => $draft->slug]);
});

it('lets an accepted judge score every criterion', function () {
    $judge = Judge::factory()->for($this->competition)->create();
    $flow = Criterion::factory()->for($this->competition)->create(['max_points' => 10]);
    $lyrics = Criterion::factory()->for($this->competition)->create(['max_points' => 20]);
    $url = "/api/competitions/{$this->competition->slug}/matches/{$this->match->id}/jury-scores";

    $this->actingAs($judge->user)->postJson($url, [
        'participant_id' => $this->a->id,
        'scores' => [['criterion_id' => $flow->id, 'score' => 11], ['criterion_id' => $lyrics->id, 'score' => 15]],
    ])->assertJsonValidationErrors('scores.0.score');

    $this->actingAs($judge->user)->postJson($url, [
        'participant_id' => $this->a->id,
        'scores' => [['criterion_id' => $flow->id, 'score' => 8], ['criterion_id' => $lyrics->id, 'score' => 15]],
    ])->assertCreated();

    expect($this->match->juryScores()->count())->toBe(2);
});

it('hides the jury endpoint from non-judges', function () {
    $this->actingAs(User::factory()->create())
        ->postJson("/api/competitions/{$this->competition->slug}/matches/{$this->match->id}/jury-scores", [])
        ->assertNotFound();
});

it('registers an artist and forbids judges from registering', function () {
    $open = Competition::factory()->create(['status' => CompetitionStatus::Registration]);
    $artist = User::factory()->create();

    $this->actingAs($artist)
        ->postJson("/api/competitions/{$open->slug}/registrations", ['stage_name' => 'MC Test'])
        ->assertCreated()
        ->assertJsonPath('status', 'inscrit');

    $this->actingAs($artist)
        ->postJson("/api/competitions/{$open->slug}/registrations", ['stage_name' => 'MC Test'])
        ->assertForbidden();

    $judge = Judge::factory()->for($open)->create();

    $this->actingAs($judge->user)
        ->postJson("/api/competitions/{$open->slug}/registrations", ['stage_name' => 'Judge'])
        ->assertForbidden();
});

it('keeps the database consistent when a duplicate vote hits the unique constraint', function () {
    $voter = User::factory()->create();
    $this->actingAs($voter)->postJson($this->voteUrl, ['participant_id' => $this->a->id])->assertCreated();

    // Simulate the race: the pre-check passed but the row already exists.
    $vote = new PublicVote(['match_id' => $this->match->id, 'participant_id' => $this->b->id]);
    $vote->user_id = $voter->id;

    expect(fn () => DB::transaction(fn () => $vote->save()))
        ->toThrow(UniqueConstraintViolationException::class)
        ->and($this->match->publicVotes()->count())->toBe(1);
});

it('closes the public vote first, then lets the jury deliberate until the organizer deadline', function () {
    $judge = Judge::factory()->for($this->competition)->create();
    $criterion = Criterion::factory()->for($this->competition)->create(['max_points' => 10]);
    $this->match->forceFill(['voting_opens_at' => now(), 'voting_closes_at' => now()->addMinutes(5), 'deliberation_ends_at' => now()->addMinutes(15)])->save();
    $juryUrl = "/api/competitions/{$this->competition->slug}/matches/{$this->match->id}/jury-scores";
    $score = fn (Participant $p) => ['participant_id' => $p->id, 'scores' => [['criterion_id' => $criterion->id, 'score' => $p->is($this->a) ? 8 : 6]]];

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($this->voteUrl, ['participant_id' => $this->a->id])->assertCreated();

    // Deliberation: public vote closed, jury still scores, the scheduler does not close the match.
    $this->travel(7)->minutes();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($this->voteUrl, ['participant_id' => $this->a->id])->assertForbidden();
    $this->actingAs($judge->user, 'sanctum')->postJson($juryUrl, $score($this->a))->assertCreated();
    $this->actingAs($judge->user, 'sanctum')->postJson($juryUrl, $score($this->b))->assertCreated();
    expect($this->match->fresh()->isDeliberating())->toBeTrue();
    $this->artisan('matches:close-expired');
    expect($this->match->fresh()->status)->toBe(MatchStatus::Voting);

    // Deliberation over: scores locked, the match closes automatically.
    $this->travel(10)->minutes();
    $this->actingAs($judge->user, 'sanctum')->postJson($juryUrl, $score($this->a))->assertForbidden();
    $this->artisan('matches:close-expired');
    expect($this->match->fresh())->status->toBe(MatchStatus::Closed)->winner_id->toBe($this->a->id);
});
