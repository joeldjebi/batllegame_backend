<?php

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseType;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(30);
});

it('guides an artist through the groups, the submission and the results, up to the final', function () {
    ['competition' => $competition, 'phase' => $groups, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 8, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], ['submissions_require_approval' => false], qualifiers: 2);
    Phase::factory()->for($competition)->create(['type' => PhaseType::SingleElimination, 'position' => 2, 'rules' => ['vote_mode' => 'public']]);
    $artist = $p[0];
    $page = fn () => $this->actingAs($artist->user, 'member')->get(route('artist.competitions.show', $competition));

    // Groups built, submissions not open yet.
    $page()->assertOk()->assertSee('Phase 1 · Poules')->assertSee('Poule A')->assertSee('toi + 3 artiste(s)', false)
        ->assertSee('Envoi pas encore ouvert')->assertSee('Demi-finales')->assertSee('Finale')->assertDontSee('Quarts de finale');

    // Submissions open: the upload form of this stage.
    $stage = $groups->stages()->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addDay()]);
    app(StageService::class)->openSubmissions($stage);
    $page()->assertSee('Envoyer ma prestation')->assertSee(route('artist.competitions.stages.submit', [$competition, $stage]));

    $this->actingAs($artist->user, 'member')->post(route('artist.competitions.stages.submit', [$competition, $stage]), ['media' => fakeVideo()])->assertSessionHasNoErrors();
    $page()->assertSee('Prestation validée');

    // Results published: qualified.
    $groups->matches->each(fn ($match) => $match->forceFill(['status' => MatchStatus::Voting])->save());
    $groups->matches()->get()->each(fn ($match) => app(MatchCloser::class)->close($match));
    app(GroupResultsService::class)->publish($groups->fresh());
    $page()->assertSee('Qualifié !');

    // Another artist eliminated at the groups: their journey ends.
    $loser = $p->first(fn ($participant) => $participant->fresh()->status === ParticipantStatus::Eliminated);
    $this->actingAs($loser->user, 'member')->get(route('artist.competitions.show', $competition))->assertOk()->assertSee('Parcours terminé')->assertSee('Non qualifié');
});

it('is only open to the artists of the competition', function () {
    ['competition' => $competition] = startedCompetition(CompetitionMode::Online, 2);

    $this->actingAs(User::factory()->create(), 'member')->get(route('artist.competitions.show', $competition))->assertNotFound();
});
