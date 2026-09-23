<?php

use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\PhaseLauncher;

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create(['email' => 'sa@example.com']);
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create(['name' => 'Abidjan Battle League']);
    $this->competition = Competition::factory()->for($this->organizer)->create(['name' => 'Rap Contest 2026']);

    $participants = Participant::factory()->for($this->competition)->count(4)->sequence(fn ($s) => ['seed' => $s->index + 1])->create();
    Judge::factory()->for($this->competition)->create();
    Criterion::factory()->for($this->competition)->create(['name' => 'Flow']);
    $phase = Phase::factory()->for($this->competition)->create(['type' => PhaseType::SingleElimination, 'rules' => ['vote_mode' => 'public']]);
    app(PhaseLauncher::class)->start($phase);

    $this->artist = $participants->first()->user;
});

it('shows the platform overview', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("Vue d'ensemble")
        ->assertSee('Compétitions par statut')
        ->assertSee('Rap Contest 2026');
});

it('shows every detail of an organizer', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizers.show', $this->organizer))
        ->assertOk()
        ->assertSee('Abidjan Battle League')
        ->assertSee('Rap Contest 2026')
        ->assertSee('owner@example.com');
});

it('lists and filters all competitions of the platform', function () {
    Competition::factory()->draft()->create(['name' => 'Autre compétition']);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.competitions.index', ['status' => 'en_cours']))
        ->assertOk()
        ->assertDontSee('Autre compétition');

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.competitions.index', ['q' => 'Abidjan']))
        ->assertOk()
        ->assertSee('Rap Contest 2026');
});

it('shows a competition in read-only mode with its bracket', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()
        ->assertSee('Lecture seule')
        ->assertSee('Demi-finales')
        ->assertSee('Flow')
        ->assertDontSee('Ouvrir le vote')
        ->assertDontSee('Clôturer');
});

it('does not resolve a competition through another organizer', function () {
    $other = Organizer::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.organizers.competitions.show', [$other, $this->competition]))
        ->assertNotFound();
});

it('lists and details user accounts', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', ['type' => 'backoffice']))
        ->assertOk()
        ->assertSee('owner@example.com')
        ->assertDontSee($this->artist->phone);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.show', $this->artist))
        ->assertOk()
        ->assertSee('Rap Contest 2026');
});

it('keeps every admin page closed to organizer sessions', function () {
    foreach ([
        route('admin.dashboard'),
        route('admin.organizers.show', $this->organizer),
        route('admin.competitions.index'),
        route('admin.organizers.competitions.show', [$this->organizer, $this->competition]),
        route('admin.users.index'),
        route('admin.users.show', $this->artist),
    ] as $url) {
        $this->actingAs($this->owner)->get($url)->assertRedirect(route('admin.login'));
    }
});
