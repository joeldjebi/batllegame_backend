<?php

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\PhaseType;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(30);
});

it('gives the artist their journey: group, stage to submit, then the results', function () {
    ['competition' => $competition, 'phase' => $groups, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 8, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], ['submissions_require_approval' => false], qualifiers: 2);
    Phase::factory()->for($competition)->create(['type' => PhaseType::SingleElimination, 'position' => 2, 'rules' => ['vote_mode' => 'public']]);
    $artist = $p[0];
    $journey = fn () => $this->actingAs($artist->user, 'sanctum')->getJson("/api/me/participations/{$competition->slug}")->assertOk();

    $data = $journey()->json('data');
    expect($data['participant']['stage_name'])->toBe($artist->stage_name)
        ->and($data['phases'])->toHaveCount(2)
        ->and($data['phases'][0]['title'])->toBe('Phase 1 · Poules')
        ->and($data['phases'][0]['stages'][0]['match']['is_group'])->toBeTrue()
        ->and($data['phases'][0]['stages'][0]['match']['others'])->toHaveCount(3)
        ->and($data['phases'][0]['stages'][0]['label'])->toBe('Envoi pas encore ouvert')
        ->and(collect($data['phases'][1]['stages'])->pluck('name')->all())->toBe(['Demi-finales', 'Finale']);

    $stage = $groups->stages()->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addDay()]);
    app(StageService::class)->openSubmissions($stage);

    $data = $journey()->json('data');
    expect($data['next'])->toMatchArray(['type' => 'submit', 'stage_id' => $stage->id])
        ->and($data['next']['deadline'])->not->toBeNull()
        ->and($data['phases'][0]['stages'][0]['media_rules'])->toHaveKeys(['types', 'max_duration_seconds', 'max_size_mb']);

    $this->actingAs($artist->user, 'sanctum')->postJson("/api/competitions/{$competition->slug}/stages/{$stage->id}/submission", ['media' => fakeVideo()])->assertCreated();
    expect($journey()->json('data.phases.0.stages.0.submission.status'))->toBe('validee');

    $groups->matches->each(fn ($match) => $match->forceFill(['status' => MatchStatus::Voting])->save());
    $groups->matches()->get()->each(fn ($match) => app(MatchCloser::class)->close($match));
    app(GroupResultsService::class)->publish($groups->fresh());

    expect($journey()->json('data.phases.0.stages.0'))->toMatchArray(['state' => 'won'])
        ->and($journey()->json('data.phases.0.stages.0.match.my_rank'))->not->toBeNull();
});

it('includes the pre-selection with the artist entry and whether they can submit', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $artist = $artists[0];

    $this->actingAs($artist->user, 'sanctum')->getJson("/api/me/participations/{$competition->slug}")->assertOk()
        ->assertJsonPath('data.preselection.can_submit', true)
        ->assertJsonPath('data.preselection.entry', null);

    preselectionEntry($artist);
    $this->getJson("/api/me/participations/{$competition->slug}")
        ->assertJsonPath('data.preselection.entry.status', 'validee')
        ->assertJsonPath('data.preselection.result', null);
});

it('is only open to the artists of the competition', function () {
    ['competition' => $competition] = startedCompetition(CompetitionMode::Online, 2);

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/me/participations/{$competition->slug}")->assertNotFound();
    auth()->forgetGuards();
    $this->getJson("/api/me/participations/{$competition->slug}")->assertUnauthorized();
});

it('lists my competitions with their id (realtime events carry competition_id)', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);

    $this->actingAs($artists[0]->user, 'sanctum')->getJson('/api/me/participations')->assertOk()
        ->assertJsonPath('data.0.competition.id', $competition->id)
        ->assertJsonPath('data.0.competition.slug', $competition->slug);
});
