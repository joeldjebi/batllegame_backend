<?php

use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Phase;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->competition = Competition::factory()->for($this->organizer)->create();
    $this->phase = fn (array $data = []) => [
        'type' => 'poules', 'mode' => 'en_ligne', 'qualifiers_per_group' => 2,
        'rules' => ['group_count' => 4, 'expected_entrants' => 16, 'vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40], ...$data,
    ];
});

it('edits a phase until it starts', function () {
    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.phases.store', [$this->organizer, $this->competition]), ($this->phase)())->assertSessionHasNoErrors();
    $phase = $this->competition->phases()->first();

    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()->assertSee('Modifier la phase 1');

    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.phases.update', [$this->organizer, $this->competition, $phase]), ($this->phase)([
        'qualifiers_per_group' => 1,
        'rules' => ['group_count' => 2, 'expected_entrants' => 16, 'vote_mode' => 'public', 'tie_breakers' => ['public', 'jury', 'seed']],
    ]))->assertSessionHasNoErrors();

    $phase->refresh();
    expect($phase->qualifiers_per_group)->toBe(1)
        ->and($phase->rules->groupCount)->toBe(2)
        ->and($phase->rules->tieBreakers[0]->value)->toBe('public');
});

it('only offers the sequences the engine can play', function () {
    $store = route('organizers.competitions.phases.store', [$this->organizer, $this->competition]);

    // Groups bring their final bracket along.
    $this->actingAs($this->owner, 'web')->post($store, ($this->phase)())->assertSessionHasNoErrors();

    // Nothing after an elimination phase.
    $this->actingAs($this->owner, 'web')->post($store, ['type' => 'elimination', 'mode' => 'en_ligne', 'rules' => ['vote_mode' => 'jury']])->assertSessionHasErrors('type');

    // The groups are followed by a phase: they cannot become an elimination.
    $groups = $this->competition->phases()->orderBy('position')->first();
    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.phases.update', [$this->organizer, $this->competition, $groups]), ['type' => 'elimination', 'mode' => 'en_ligne', 'rules' => ['vote_mode' => 'jury']])
        ->assertSessionHasErrors('type');

    expect($this->competition->phases()->count())->toBe(2)
        ->and(Phase::query()->where('type', PhaseType::SingleElimination)->value('qualifiers_per_group'))->toBeNull();

    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()->assertSee('aucune phase ne peut la suivre')->assertDontSee('Nouvelle phase');
});

it('does not edit a started phase', function () {
    $phase = Phase::factory()->for($this->competition)->started()->create();

    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.phases.update', [$this->organizer, $this->competition, $phase]), ['type' => 'elimination', 'mode' => 'en_ligne', 'rules' => ['vote_mode' => 'jury']])
        ->assertRedirect();

    expect($phase->fresh()->type)->toBe($phase->type);
});
