<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Phase;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->competition = Competition::factory()->for($this->organizer)->create(['status' => CompetitionStatus::Registration, 'entry_fee' => 5000]);
});

it('saves the schedule and the regulations written by the organizer', function () {
    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.update', [$this->organizer, $this->competition]), [
        'regulations' => '<h2>Conduite</h2><div>Respect<script>alert(1)</script></div>',
        'schedule' => [
            ['title' => 'Demi-finales', 'date' => '2026-11-14T20:00', 'details' => 'Palais de la culture'],
            ['title' => '', 'date' => null, 'details' => 'ignored'],
            ['title' => 'Finale', 'date' => null, 'details' => null],
        ],
    ])->assertSessionHasNoErrors();

    $competition = $this->competition->fresh();
    expect($competition->regulations)->toContain('Respect')->not->toContain('script')
        ->and($competition->scheduleList())->toBe([
            ['title' => 'Demi-finales', 'date' => '2026-11-14T20:00', 'details' => 'Palais de la culture'],
            ['title' => 'Finale', 'date' => null, 'details' => null],
        ]);

    $this->get(route('fan.competitions.show', $competition))->assertOk()
        ->assertSee('Déroulé')->assertSee('Demi-finales')->assertSee('Lire le règlement')->assertSee('Respect');
    $this->getJson("/api/competitions/{$competition->slug}")->assertOk()
        ->assertJsonPath('data.schedule.0.title', 'Demi-finales')
        ->assertJsonPath('data.schedule.1.title', 'Finale');
});

it('drafts both from the configuration without saving them', function () {
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::Groups, 'position' => 1, 'qualifiers_per_group' => 2, 'rules' => ['vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40, 'group_count' => 4]]);
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::SingleElimination, 'position' => 2, 'rules' => ['vote_mode' => 'jury']]);

    $response = $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.guide.draft', [$this->organizer, $this->competition]))
        ->assertRedirect()->assertSessionHas('status');

    $old = $response->getSession()->getOldInput();
    expect($old['regulations'])->toContain('4 poule(s)')->toContain('Ils ne s&#039;affrontent pas')->toContain('jury 60 % et public 40 %')->toContain('5 000 XOF')
        ->and(collect($old['schedule'])->pluck('title')->all())->toBe(['Clôture des inscriptions', 'Phase 1 · Poules', 'Quarts de finale', 'Demi-finales', 'Finale', 'Résultats et remise des prix'])
        ->and($this->competition->fresh()->regulations)->toBeNull();

    // The settings form shows the draft, ready to be edited and saved.
    $this->actingAs($this->owner, 'web')->withSession(['_old_input' => $old])
        ->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()->assertSee('Résultats et remise des prix');
});

it('invites artists to read the regulations before registering', function () {
    $this->competition->update(['regulations' => '<div>Règles</div>']);
    $artist = User::factory()->create();

    $this->actingAs($artist, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('tu acceptes le', false)->assertSee('#reglement', false);
});
