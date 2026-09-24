<?php

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\User;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Stages/helpers.php';

it('shows the groups, then lets the organizer publish the ranking', function () {
    ['phase' => $phase, 'owner' => $owner, 'organizer' => $organizer, 'competition' => $competition] = startedCompetition(CompetitionMode::OnSite, 6, PhaseType::Groups, ['group_count' => 2], qualifiers: 1);
    $show = route('organizers.competitions.show', [$organizer, $competition]);

    $this->actingAs($owner, 'web')->get($show)->assertOk()
        ->assertSee('Poule A')->assertSee('Ouvrir le vote de la poule')->assertDontSee('Publier les résultats');

    // Public page: a group votes as a whole, once per phase.
    $phase->matches->each(fn ($match) => $match->forceFill(['status' => MatchStatus::Voting])->save());
    $this->actingAs(User::factory()->create(), 'member')->get(route('fan.competitions.show', $competition))->assertOk()
        ->assertSee('Un seul vote pour toute la phase')->assertSee('Voter pour Seed 1');

    $phase->matches()->get()->each(fn ($match) => app(MatchCloser::class)->close($match));
    $this->actingAs($owner, 'web')->get($show)->assertOk()->assertSee('Publier les résultats');

    $this->actingAs($owner, 'web')->post(route('organizers.competitions.phases.publish', [$organizer, $competition, $phase]))->assertRedirect()->assertSessionHas('status');

    expect($phase->fresh()->status)->toBe(PhaseStatus::Finished);
    $this->get(route('fan.competitions.show', $competition))->assertOk()->assertSee('Résultats des poules')->assertSee('Qualifié');
});

it('lists the submissions of an online group stage per group, with who has not sent yet', function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(30);
    ['phase' => $phase, 'owner' => $owner, 'organizer' => $organizer, 'competition' => $competition, 'participants' => $p] = startedCompetition(CompetitionMode::Online, 6, PhaseType::Groups, ['group_count' => 2, 'draw_method' => 'seed'], qualifiers: 1);
    $stage = $phase->stages()->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addDay()]);
    app(StageService::class)->openSubmissions($stage);
    $this->actingAs($p[0]->user, 'sanctum')->postJson("/api/competitions/{$competition->slug}/stages/{$stage->id}/submission", ['media' => fakeVideo()])->assertCreated();

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertOk()
        ->assertSee('soumission(s) par poule')->assertSee('1 / 3 envoyée(s)')->assertSee('0 / 3 envoyée(s)')->assertSee('Pas encore envoyée');
});
