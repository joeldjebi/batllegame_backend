<?php

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Enums\StageStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competition\PhaseLauncher;
use App\Services\Competition\StageService;
use App\Services\SubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

function submitAs(Participant $participant, Stage $stage, $file = null)
{
    return test()->actingAs($participant->user, 'sanctum')
        ->postJson("/api/competitions/{$stage->phase->competition->slug}/stages/{$stage->id}/submission", ['media' => $file ?? fakeVideo()]);
}

function openStage(Stage $stage): Stage
{
    $stages = app(StageService::class);
    $stages->schedule($stage, ['submission_deadline' => now()->addDay()]);

    return $stages->openSubmissions($stage);
}

it('builds one stage for a group phase and one per bracket round', function () {
    ['phase' => $groups] = startedCompetition(CompetitionMode::Online, 8, PhaseType::Groups, ['group_count' => 2], qualifiers: 2);
    ['phase' => $single] = startedCompetition(CompetitionMode::Online, 8);
    ['phase' => $double] = startedCompetition(CompetitionMode::Online, 8, PhaseType::DoubleElimination);

    expect($groups->stages()->pluck('name')->all())->toBe(['Poules'])
        ->and($groups->matches()->whereNull('stage_id')->count())->toBe(0)
        ->and($single->stages()->pluck('name')->all())->toBe(['Quarts de finale', 'Demi-finales', 'Finale'])
        ->and($double->stages()->count())->toBe(3 + 4 + 1)
        ->and($double->stages()->first()->name)->toBe('Principal · Quarts de finale');
});

it('requires an explicit online or on-site mode for phases of a mixed competition', function () {
    ['competition' => $competition] = startedCompetition(CompetitionMode::OnSite);
    $competition->update(['mode' => CompetitionMode::Hybrid]);
    $phase = Phase::factory()->for($competition)->create(['mode' => null]);

    app(PhaseLauncher::class)->start($phase);
})->throws(CompetitionFlowException::class, 'mixte');

it('only opens submissions on online stages whose participants are known', function () {
    ['phase' => $phase] = startedCompetition(CompetitionMode::Online);
    [$semis, $final] = $phase->stages()->get();

    expect(fn () => app(StageService::class)->openSubmissions($semis))->toThrow(CompetitionFlowException::class, 'date limite');

    openStage($semis);

    expect($semis->fresh()->status)->toBe(StageStatus::Submissions)
        ->and($semis->matches()->pluck('status')->unique()->all())->toBe([MatchStatus::Submissions])
        ->and(fn () => openStage($final))->toThrow(CompetitionFlowException::class, 'pas encore tous connus');

    ['phase' => $onsite] = startedCompetition(CompetitionMode::OnSite);
    expect(fn () => openStage($onsite->stages()->first()))->toThrow(CompetitionFlowException::class, 'en ligne');
});

it('accepts one submission per participant and stage, replacing the previous one', function () {
    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online);
    $stage = openStage($phase->stages()->first());

    submitAs($participants[0], $stage)->assertCreated();
    $first = Performance::query()->firstOrFail();
    submitAs($participants[0], $stage, fakeVideo('take2.mp4'))->assertCreated();

    $performance = Performance::query()->sole();

    expect($performance->status)->toBe(PerformanceStatus::Pending)
        ->and($performance->duration_seconds)->toBe(60)
        ->and($performance->competition_id)->toBe($phase->competition_id)
        ->and($performance->original_name)->toBe('take2.mp4');

    Storage::disk('public')->assertMissing($first->media_path);
    Storage::disk('public')->assertExists($performance->media_path);
});

it('validates submissions against the phase media rules', function () {
    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online, rules: ['media_types' => ['audio'], 'media_max_size_mb' => 1]);
    $stage = openStage($phase->stages()->first());

    submitAs($participants[0], $stage, fakeVideo())->assertJsonValidationErrors('media');
    submitAs($participants[0], $stage, UploadedFile::fake()->create('song.mp3', 2048, 'audio/mpeg'))->assertJsonValidationErrors('media');
    submitAs($participants[0], $stage, UploadedFile::fake()->create('song.mp3', 300, 'audio/mpeg'))->assertCreated();
});

it('rejects a media longer than allowed', function () {
    fakeMediaDuration(400);
    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online, rules: ['media_max_duration' => 120]);
    $stage = openStage($phase->stages()->first());

    submitAs($participants[0], $stage)->assertCreated();

    expect(Performance::query()->sole())
        ->status->toBe(PerformanceStatus::Rejected)
        ->rejection_reason->toContain('120 s');
});

it('refuses submissions from outsiders and after the deadline', function () {
    ['phase' => $phase, 'participants' => $participants, 'competition' => $competition] = startedCompetition(CompetitionMode::Online);
    $stage = openStage($phase->stages()->first());
    $outsider = Participant::factory()->for($competition)->create();

    submitAs($outsider, $stage)->assertForbidden();

    $this->travel(2)->days();
    submitAs($participants[0], $stage)->assertStatus(422);
});

it('turns missing submissions into forfeits at the deadline', function () {
    ['phase' => $phase, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 4, settings: ['submissions_require_approval' => false]);
    [$semis, $final] = $phase->stages()->get();
    openStage($semis);

    // Semi 1: seed 1 vs 4 -> only seed 4 submits. Semi 2: seed 2 vs 3 -> nobody submits.
    submitAs($p[3], $semis)->assertCreated();

    $this->travel(2)->days();
    $this->artisan('stages:process')->assertSuccessful();

    $semi1 = $semis->matches()->where('bracket_position', 1)->first();
    $semi2 = $semis->matches()->where('bracket_position', 2)->first();
    $finalMatch = $final->matches()->first();

    expect($semi1->status)->toBe(MatchStatus::Closed)
        ->and($semi1->is_forfeit)->toBeTrue()
        ->and($semi1->winner_id)->toBe($p[3]->id)
        ->and($p[0]->fresh()->status)->toBe(ParticipantStatus::Eliminated)
        ->and($semi2->status)->toBe(MatchStatus::Cancelled)
        ->and($p[1]->fresh()->status)->toBe(ParticipantStatus::Withdrawn)
        ->and($p[2]->fresh()->status)->toBe(ParticipantStatus::Withdrawn)
        // Nobody left in front of seed 4: the final is a walkover and the phase is over.
        ->and($finalMatch->status)->toBe(MatchStatus::Closed)
        ->and($finalMatch->winner_id)->toBe($p[3]->id)
        ->and($semis->fresh()->status)->toBe(StageStatus::Closed)
        ->and($phase->fresh()->status)->toBe(PhaseStatus::Finished);
});

it('counts a group forfeit as a loss, and a double forfeit as two losses', function () {
    ['phase' => $phase, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 3, PhaseType::Groups, ['group_count' => 1, 'draw_method' => 'seed'], ['submissions_require_approval' => false], qualifiers: 1);
    $stage = openStage($phase->stages()->first());
    submitAs($p[0], $stage)->assertCreated();

    $this->travel(2)->days();
    app(StageService::class)->applyForfeits($stage);

    $standings = $phase->groups()->first()->standings()->with('participant')->get()->keyBy(fn ($s) => $s->participant->seed);

    expect($standings[1]->wins)->toBe(2)
        ->and($standings[1]->points)->toBe(6)
        ->and($standings[2]->losses)->toBe(2)
        ->and($standings[3]->losses)->toBe(2)
        ->and($standings[2]->points + $standings[3]->points)->toBe(0);
});

it('waits for the organizer review before opening the vote', function () {
    ['phase' => $phase, 'participants' => $p, 'owner' => $owner] = startedCompetition(CompetitionMode::Online, 2);
    $stage = openStage($phase->stages()->first());
    submitAs($p[0], $stage);
    submitAs($p[1], $stage);
    $this->travel(2)->days();

    expect(fn () => app(StageService::class)->openVoting($stage))->toThrow(CompetitionFlowException::class, '2 soumission(s)');

    Performance::query()->get()->each(fn ($perf) => app(SubmissionService::class)->approve($perf, $owner));
    app(StageService::class)->openVoting($stage);

    $match = $stage->matches()->first();
    expect($match->fresh()->status)->toBe(MatchStatus::Voting);

    $this->getJson("/api/competitions/{$phase->competition->slug}/matches/{$match->id}")
        ->assertOk()
        ->assertJsonPath('data.slots.0.media.0.type', 'video')
        ->assertJsonPath('data.stage.name', 'Finale');
});

it('lets the organizer review submissions from the back-office', function () {
    ['phase' => $phase, 'participants' => $p, 'owner' => $owner, 'organizer' => $organizer, 'competition' => $competition] = startedCompetition(CompetitionMode::Online, 2);
    $stage = openStage($phase->stages()->first());
    submitAs($p[0], $stage);
    $performance = Performance::query()->sole();

    $this->actingAs($owner, 'web')
        ->patch(route('organizers.competitions.performances.review', [$organizer, $competition, $performance]), ['decision' => 'reject'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($owner, 'web')
        ->patch(route('organizers.competitions.performances.review', [$organizer, $competition, $performance]), ['decision' => 'reject', 'reason' => 'Son inaudible'])
        ->assertSessionHasNoErrors();

    expect($performance->fresh())->status->toBe(PerformanceStatus::Rejected)->rejection_reason->toBe('Son inaudible');

    // A performance of another competition is never reachable through this one.
    ['competition' => $other, 'organizer' => $otherOrganizer] = startedCompetition(CompetitionMode::Online, 2);
    $this->actingAs($owner, 'web')
        ->patch("/organizers/{$organizer->slug}/competitions/{$other->id}/performances/{$performance->id}", ['decision' => 'approve'])
        ->assertNotFound();

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertOk()->assertSee('Son inaudible');
});

it('runs a complete online elimination stage by stage', function () {
    ['phase' => $phase, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 4, settings: ['submissions_require_approval' => false]);
    $stages = app(StageService::class);
    [$semis, $final] = $phase->stages()->get();

    $stages->schedule($semis, ['submission_deadline' => now()->addDay(), 'voting_closes_at' => now()->addDays(3)]);
    $stages->openSubmissions($semis);
    $p->each(fn ($participant) => submitAs($participant, $semis)->assertCreated());

    $this->travel(25)->hours();
    $this->artisan('stages:process')->assertSuccessful();
    expect($semis->fresh()->status)->toBe(StageStatus::Voting);

    // The public votes for the best seeds, then the voting window ends.
    foreach ($semis->matches()->with('slots.participant')->get() as $match) {
        $favorite = $match->slots->sortBy(fn ($s) => $s->participant->seed)->first()->participant_id;
        $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $favorite]);
        $vote->user_id = User::factory()->create()->id;
        $vote->save();
    }

    $this->travel(3)->days();
    $this->artisan('matches:close-expired')->assertSuccessful();

    expect($semis->fresh()->status)->toBe(StageStatus::Closed)
        ->and($final->matches()->first()->slots()->with('participant')->get()->pluck('participant.seed')->all())->toBe([1, 2]);

    // Next stage: new submissions.
    openStage($final->fresh());
    expect($final->fresh()->status)->toBe(StageStatus::Submissions);
});
