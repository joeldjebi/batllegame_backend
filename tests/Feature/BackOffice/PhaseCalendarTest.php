<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\PhaseCalendar;
use App\Services\Competition\PhaseLauncher;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->competition = Competition::factory()->for($this->organizer)->create([
        'status' => CompetitionStatus::Registration, 'mode' => CompetitionMode::Online, 'settings' => ['timezone' => 'Africa/Abidjan'],
    ]);
});

it('creates the final bracket with the groups, so the calendar is known up to the final', function () {
    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.phases.store', [$this->organizer, $this->competition]), [
        'type' => 'poules', 'mode' => 'en_ligne', 'qualifiers_per_group' => 2,
        'rules' => ['group_count' => 4, 'expected_entrants' => 16, 'vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40, 'media_max_duration' => 120],
    ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'quarts de finale, demi-finales, finale'));

    [$groups, $final] = $this->competition->phases()->get()->all();

    expect($final->type)->toBe(PhaseType::SingleElimination)
        ->and($final->position)->toBe(2)
        ->and($final->mode)->toBe(CompetitionMode::Online)
        ->and($final->rules->juryWeight)->toBe(60)
        ->and($final->rules->mediaMaxDuration)->toBe(120)
        ->and(PhaseCalendar::stageNames($groups))->toBe(['Poules'])
        ->and(PhaseCalendar::stageNames($final))->toBe(['Quarts de finale', 'Demi-finales', 'Finale']);

    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()->assertSee('Calendrier prévu')->assertSee('Demi-finales');
});

it('plans the dates of each round and applies them when the phase starts', function () {
    $this->competition->update(['max_participants' => 8]);
    Participant::factory()->for($this->competition)->count(8)->sequence(fn ($s) => ['seed' => $s->index + 1])->create(['status' => ParticipantStatus::Validated]);
    $phase = Phase::factory()->for($this->competition)->create(['type' => PhaseType::SingleElimination, 'mode' => CompetitionMode::Online, 'rules' => ['vote_mode' => 'jury']]);
    $url = route('organizers.competitions.phases.calendar', [$this->organizer, $this->competition, $phase]);

    expect(PhaseCalendar::stageNames($phase))->toBe(['Quarts de finale', 'Demi-finales', 'Finale']);

    $this->actingAs($this->owner, 'web')->put($url, ['calendar' => ['Finale' => ['voting_closes_at' => '2026-12-01T20:00', 'voting_opens_at' => '2026-12-02T20:00']]])
        ->assertSessionHasErrors('calendar.Finale.voting_closes_at');

    $this->actingAs($this->owner, 'web')->put($url, ['calendar' => [
        'Quarts de finale' => ['submission_deadline' => '2026-11-10T23:00', 'voting_closes_at' => '2026-11-12T20:00', 'deliberation_minutes' => 120],
        'Finale' => ['submission_deadline' => '2026-12-01T12:00', 'voting_closes_at' => '2026-12-02T21:00'],
    ]])->assertSessionHasNoErrors();

    app(PhaseLauncher::class)->start($phase->fresh());

    $stages = $phase->stages()->get()->keyBy('name');
    expect($stages['Quarts de finale']->submission_deadline->toIso8601String())->toBe('2026-11-10T23:00:00+00:00')
        ->and($stages['Quarts de finale']->deliberation_minutes)->toBe(120)
        ->and($stages['Demi-finales']->voting_closes_at)->toBeNull()
        ->and($stages['Finale']->voting_closes_at->toIso8601String())->toBe('2026-12-02T21:00:00+00:00');
});
