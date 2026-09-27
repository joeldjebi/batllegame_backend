<?php

use App\Enums\CompetitionMode;
use App\Enums\PhaseType;
use App\Enums\StageStatus;
use App\Exceptions\CompetitionFlowException;
use App\Services\Competition\GroupResultsService;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;

require_once __DIR__.'/helpers.php';

it('keeps jury notes and scores hidden until the organizer publishes the finished stage', function () {
    ['competition' => $competition, 'phase' => $phase, 'organizer' => $organizer, 'owner' => $owner] = startedCompetition(CompetitionMode::OnSite, 4, settings: ['show_live_results' => true]);
    $stage = $phase->stages()->orderBy('number')->first();
    $matches = $stage->matches()->get();
    $matches->each(fn ($match) => app(MatchCloser::class)->close($match->fresh(), $match->slots()->orderBy('slot')->value('participant_id')));
    // Scores as computed at the close (no votes in this test).
    $matches->each(fn ($match) => $match->slots()->update(['jury_score' => 80, 'public_score' => 60, 'final_score' => 70]));
    expect($stage->fresh()->status)->toBe(StageStatus::Closed);
    $first = $matches->first();

    // Closed, live results on: the public share is live, the jury never before publication.
    $this->getJson("/api/competitions/{$competition->slug}/matches/{$first->id}")
        ->assertJsonPath('data.results_published', false)
        ->assertJsonPath('data.slots.0.jury_score', null)
        ->assertJsonPath('data.slots.0.final_score', null)
        ->assertJsonPath('data.slots.0.public_score', fn ($score) => $score !== null);
    $this->getJson("/api/competitions/{$competition->slug}/matches?phase={$phase->id}")
        ->assertJsonPath('data.0.results_published', false)
        ->assertJsonPath('data.0.slots.0.jury_score', null);

    $this->actingAs($owner, 'web')->post(route('organizers.competitions.stages.publish-results', [$organizer, $competition, $stage]))->assertSessionHasNoErrors();

    expect($stage->fresh()->results_published_at)->not->toBeNull();
    $this->getJson("/api/competitions/{$competition->slug}/matches?phase={$phase->id}")
        ->assertJsonPath('data.0.results_published', true)
        ->assertJsonPath('data.0.slots.0.jury_score', 80)
        ->assertJsonPath('data.0.slots.0.final_score', 70);

    // Once only.
    $this->actingAs($owner, 'web')->post(route('organizers.competitions.stages.publish-results', [$organizer, $competition, $stage]))->assertSessionHasErrors('flow');
});

it('refuses to publish a stage still running, or a group stage outside its phase', function () {
    ['phase' => $phase] = startedCompetition(CompetitionMode::OnSite, 4);
    expect(fn () => app(StageService::class)->publishResults($phase->stages()->first()))->toThrow(CompetitionFlowException::class, 'une fois l\'étape terminée');

    ['phase' => $groups] = startedCompetition(CompetitionMode::OnSite, 4, PhaseType::Groups, ['group_count' => 2], qualifiers: 1);
    expect(fn () => app(StageService::class)->publishResults($groups->stages()->first()))->toThrow(CompetitionFlowException::class, 'avec la phase');
});

it('publishes the group stages with their phase', function () {
    ['phase' => $groups] = startedCompetition(CompetitionMode::OnSite, 4, PhaseType::Groups, ['group_count' => 2], qualifiers: 1);
    $groups->matches()->get()->each(fn ($match) => app(MatchCloser::class)->close($match));

    app(GroupResultsService::class)->publish($groups->fresh());

    expect($groups->stages()->whereNull('results_published_at')->count())->toBe(0)
        ->and($groups->matches()->first()->resultsArePublic())->toBeTrue();
});
