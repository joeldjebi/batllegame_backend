<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Phase;
use App\Models\User;
use App\Services\CompetitionGuideDraft;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->competition = Competition::factory()->for($this->organizer)->create(['status' => CompetitionStatus::Registration, 'entry_fee' => 5000]);
});

it('builds the schedule automatically, with the organizer extra steps placed by date', function () {
    $this->competition->update(['registration_ends_at' => '2026-11-01 18:00:00']);
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::Groups, 'position' => 1, 'qualifiers_per_group' => 2, 'rules' => ['vote_mode' => 'jury', 'group_count' => 4],
        'calendar' => ['Poules' => ['voting_closes_at' => '2026-11-10T20:00:00+00:00']]]);
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::SingleElimination, 'position' => 2, 'rules' => ['vote_mode' => 'jury'],
        'calendar' => ['Finale' => ['voting_closes_at' => '2026-12-05T21:00:00+00:00']]]);

    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.update', [$this->organizer, $this->competition]), [
        'regulations' => '<h2>Conduite</h2><div>Respect<script>alert(1)</script></div>',
        'schedule' => [
            ['title' => 'Conférence de presse', 'date' => '2026-11-05T10:00', 'details' => 'Sofitel'],
            ['title' => '', 'date' => null, 'details' => 'ignored'],
            ['title' => 'Soirée des artistes', 'date' => null, 'details' => null],
        ],
    ])->assertSessionHasNoErrors();

    $competition = $this->competition->fresh();
    $titles = collect(app(CompetitionGuideDraft::class)->fullSchedule($competition))->pluck('title')->all();

    expect($competition->regulations)->toContain('Respect')->not->toContain('script')
        ->and($titles)->toBe(['Clôture des inscriptions', 'Conférence de presse', 'Phase 1 · Poules', 'Quarts de finale', 'Demi-finales', 'Finale', 'Résultats et remise des prix', 'Soirée des artistes']);

    // The settings card lists the automatic steps, then the extra ones.
    $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.show', [$this->organizer, $competition]))->assertOk()
        ->assertSee('Mis à jour automatiquement')->assertSee('Quarts de finale')->assertSee('Date à planifier')->assertSee('Conférence de presse');

    $this->get(route('fan.competitions.show', $competition))->assertOk()->assertSee('Conférence de presse')->assertSee('Demi-finales')->assertSee('Lire le règlement');
    $this->getJson("/api/competitions/{$competition->slug}")->assertOk()
        ->assertJsonPath('data.schedule.1.title', 'Conférence de presse')->assertJsonPath('data.schedule.1.auto', false)
        ->assertJsonPath('data.schedule.2.auto', true);

    // Extra steps can all be removed.
    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.update', [$this->organizer, $competition]), ['schedule' => ''])->assertSessionHasNoErrors();
    expect($competition->fresh()->scheduleList())->toBe([]);
});

it('drafts the regulations from the configuration without saving them', function () {
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::Groups, 'position' => 1, 'qualifiers_per_group' => 2, 'rules' => ['vote_mode' => 'mixte', 'jury_weight' => 60, 'public_weight' => 40, 'group_count' => 4]]);
    Phase::factory()->for($this->competition)->create(['type' => PhaseType::SingleElimination, 'position' => 2, 'rules' => ['vote_mode' => 'jury']]);

    $response = $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.guide.draft', [$this->organizer, $this->competition]))
        ->assertRedirect()->assertSessionHas('status');

    $old = $response->getSession()->getOldInput();
    expect($old['regulations'])->toContain('4 poule(s)')->toContain('Ils ne s&#039;affrontent pas')->toContain('jury 60 % et public 40 %')->toContain('5 000 XOF')
        ->and($old)->not->toHaveKey('schedule')
        ->and($this->competition->fresh()->regulations)->toBeNull();
});

it('invites artists to read the regulations before registering', function () {
    $this->competition->update(['regulations' => '<div>Règles</div>']);
    $artist = User::factory()->create();

    $this->actingAs($artist, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('tu acceptes le', false)->assertSee('#reglement', false);
});
