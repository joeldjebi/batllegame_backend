<?php

use App\Enums\CompetitionMode;
use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->store = route('organizers.competitions.store', $this->organizer);
    $this->base = ['_form' => 'create-competition', 'name' => 'Yop Battle 2026', 'discipline' => 'rap', 'mode' => 'en_ligne', 'max_participants' => 64, 'entry_fee' => 5000];
});

it('creates the competition, its pre-selection and the phases up to the final in one go', function () {
    $this->actingAs($this->owner, 'web')->post($this->store, [...$this->base,
        'with_preselection' => '1',
        'preselection' => [
            'ends_at' => '2026-11-10T23:00', 'vote_ends_at' => '2026-11-12T23:00', 'deliberation_hours' => 24,
            'rules' => ['selection_size' => 16, 'like_weight' => 40, 'jury_weight' => 60, 'media_types' => ['video'], 'media_max_duration' => 120, 'media_max_size_mb' => 100],
        ],
        'phase' => ['type' => 'poules', 'qualifiers_per_group' => 2, 'rules' => ['expected_entrants' => 16, 'group_count' => 4, 'draw_method' => 'seed', 'vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40]],
    ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'la présélection et les poules et la phase finale'));

    $competition = Competition::query()->where('name', 'Yop Battle 2026')->firstOrFail();
    [$groups, $final] = $competition->phases()->get()->all();

    expect($competition->preselection->rules->selectionSize)->toBe(16)
        ->and($competition->preselection->deliberation_hours)->toBe(24)
        ->and($groups->type)->toBe(PhaseType::Groups)
        ->and($groups->rules->groupCount)->toBe(4)
        ->and($final->type)->toBe(PhaseType::SingleElimination)
        ->and($final->mode)->toBeNull();

    // Planning the groups before the end of the pre-selection is refused.
    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.phases.calendar', [$this->organizer, $competition, $groups]), [
        'calendar' => ['Poules' => ['submission_deadline' => '2026-11-12T20:00']],
    ])->assertSessionHasErrors('calendar.Poules.submission_deadline');
});

it('can skip the pre-selection and choose the format later', function () {
    $this->actingAs($this->owner, 'web')->post($this->store, [...$this->base, 'with_preselection' => '0', 'phase' => ['type' => 'plus_tard']])->assertRedirect();

    $competition = Competition::query()->where('name', 'Yop Battle 2026')->firstOrFail();
    expect($competition->preselection)->toBeNull()->and($competition->phases()->count())->toBe(0);
});

it('checks every step before creating anything', function () {
    $this->actingAs($this->owner, 'web')->post($this->store, [...$this->base,
        'mode' => CompetitionMode::Hybrid->value,
        'with_preselection' => '1', 'preselection' => ['ends_at' => '2020-11-01T10:00'],
        'phase' => ['type' => 'poules', 'qualifiers_per_group' => 4, 'rules' => ['expected_entrants' => 16, 'group_count' => 4, 'vote_mode' => 'jury']],
    ])->assertSessionHasErrors(['preselection.ends_at', 'phase.mode', 'phase.rules.group_count']);

    expect(Competition::query()->count())->toBe(0);
});

it('creates the competition on a full page in three steps', function () {
    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.index', $this->organizer))->assertOk()
        ->assertSee(route('organizers.competitions.create', $this->organizer));

    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.create', $this->organizer))->assertOk()
        ->assertSee('Nouvelle compétition')->assertSee('Avec présélection')->assertSee('Poules puis phase finale')->assertSee('Jusqu\'à la finale', false);

    // Staff cannot create competitions.
    $staff = User::factory()->create();
    $this->organizer->users()->attach($staff, ['role' => OrganizerRole::Staff]);
    $this->actingAs($staff, 'web')->get(route('organizers.competitions.create', $this->organizer))->assertForbidden();
});
