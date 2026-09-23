<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;

function memberOf(Organizer $organizer, OrganizerRole $role = OrganizerRole::Owner): User
{
    $user = User::factory()->create();
    $organizer->users()->attach($user, ['role' => $role]);

    return $user;
}

beforeEach(function () {
    $this->orgA = Organizer::factory()->create();
    $this->orgB = Organizer::factory()->create();
    $this->competitionA = Competition::factory()->for($this->orgA)->create();
    $this->competitionB = Competition::factory()->for($this->orgB)->create();
    $this->ownerA = memberOf($this->orgA);
});

it('shows a competition to members of its organizer', function () {
    $this->actingAs($this->ownerA)
        ->get(route('organizers.competitions.show', [$this->orgA, $this->competitionA]))
        ->assertOk()
        ->assertSee($this->competitionA->name);
});

it('hides another organizer and its competitions behind a 404', function () {
    $this->actingAs($this->ownerA)
        ->get(route('organizers.show', $this->orgB))
        ->assertNotFound();

    $this->actingAs($this->ownerA)
        ->get(route('organizers.competitions.show', [$this->orgB, $this->competitionB]))
        ->assertNotFound();
});

it('does not resolve a competition through the wrong organizer (scoped binding)', function () {
    // Own organizer in the URL, but a competition id belonging to organizer B.
    $this->actingAs($this->ownerA)
        ->get("/organizers/{$this->orgA->slug}/competitions/{$this->competitionB->id}")
        ->assertNotFound();

    $this->actingAs($this->ownerA)
        ->put("/organizers/{$this->orgA->slug}/competitions/{$this->competitionB->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($this->competitionB->fresh()->name)->not->toBe('Hijacked');
});

it('does not resolve a phase through the wrong competition (scoped binding)', function () {
    $phaseB = Phase::factory()->for($this->competitionB)->create();

    $this->actingAs($this->ownerA)
        ->delete("/organizers/{$this->orgA->slug}/competitions/{$this->competitionA->id}/phases/{$phaseB->id}")
        ->assertNotFound();

    expect($phaseB->fresh())->not->toBeNull();
});

it('lets staff run the event but not configure it', function () {
    $staff = memberOf($this->orgA, OrganizerRole::Staff);

    $this->actingAs($staff)
        ->get(route('organizers.competitions.show', [$this->orgA, $this->competitionA]))
        ->assertOk();

    $this->actingAs($staff)
        ->post(route('organizers.competitions.store', $this->orgA), ['name' => 'X', 'discipline' => 'rap', 'mode' => 'presentiel'])
        ->assertForbidden();

    $this->actingAs($staff)
        ->post(route('organizers.competitions.criteria.store', [$this->orgA, $this->competitionA]), ['name' => 'Flow', 'max_points' => 10, 'weight' => 1])
        ->assertForbidden();
});

it('lets admins create competitions but only owners delete them', function () {
    $admin = memberOf($this->orgA, OrganizerRole::Admin);

    $this->actingAs($admin)
        ->post(route('organizers.competitions.store', $this->orgA), ['name' => 'Battle Abidjan', 'discipline' => 'rap', 'mode' => 'mixte'])
        ->assertRedirect();

    $competition = $this->orgA->competitions()->where('name', 'Battle Abidjan')->firstOrFail();

    expect($competition->status)->toBe(CompetitionStatus::Draft)
        ->and($competition->created_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->delete(route('organizers.competitions.destroy', [$this->orgA, $competition]))
        ->assertForbidden();

    $this->actingAs($this->ownerA)
        ->delete(route('organizers.competitions.destroy', [$this->orgA, $competition]))
        ->assertRedirect();

    expect($competition->fresh()->trashed())->toBeTrue();
});

it('blocks every write on a suspended organizer but keeps it readable', function () {
    $this->orgA->forceFill(['status' => 'suspendu'])->save();

    $this->actingAs($this->ownerA)
        ->get(route('organizers.competitions.show', [$this->orgA, $this->competitionA]))
        ->assertOk();

    $this->actingAs($this->ownerA)
        ->put(route('organizers.competitions.update', [$this->orgA, $this->competitionA]), ['name' => 'New name'])
        ->assertForbidden();
});

it('requires a verified organizer to open registrations', function () {
    $pending = Organizer::factory()->pending()->create();
    $owner = memberOf($pending);
    $draft = Competition::factory()->for($pending)->draft()->create();

    $this->actingAs($owner)
        ->patch(route('organizers.competitions.status', [$pending, $draft]), ['status' => 'inscriptions'])
        ->assertForbidden();

    $this->actingAs($this->ownerA)
        ->patch(route('organizers.competitions.status', [$this->orgA, Competition::factory()->for($this->orgA)->draft()->create()]), ['status' => 'inscriptions'])
        ->assertRedirect();
});

it('rejects invalid status transitions', function () {
    $this->actingAs($this->ownerA)
        ->patch(route('organizers.competitions.status', [$this->orgA, $this->competitionA]), ['status' => 'brouillon'])
        ->assertForbidden();
});

it('reports phase rules errors under the rules key', function () {
    $this->actingAs($this->ownerA)
        ->post(route('organizers.competitions.phases.store', [$this->orgA, $this->competitionA]), [
            'type' => 'elimination',
            'rules' => ['vote_mode' => 'mixte', 'jury_weight' => 80, 'public_weight' => 80],
        ])
        ->assertSessionHasErrors('rules.jury_weight');
});

it('lets the platform admin moderate organizers from the admin area', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.organizers.status', $this->orgB), ['status' => 'suspendu'])
        ->assertRedirect();

    expect($this->orgB->fresh()->isSuspended())->toBeTrue();
});

it('keeps the admin area closed to organizer sessions', function () {
    $this->actingAs($this->ownerA)
        ->get(route('admin.organizers.index'))
        ->assertRedirect(route('admin.login'));

    // Even a non-admin user forced into the admin guard is rejected.
    $this->actingAs($this->ownerA, 'admin')
        ->get(route('admin.organizers.index'))
        ->assertForbidden();
});

it('never leaves an organizer without an owner', function () {
    $member = $this->orgA->members()->where('user_id', $this->ownerA->id)->firstOrFail();

    $this->actingAs($this->ownerA)
        ->patch(route('organizers.members.update', [$this->orgA, $member]), ['role' => 'staff'])
        ->assertSessionHasErrors('role');
});

it('renders every back-office page with data', function () {
    Country::factory()->ivoryCoast();
    Phase::factory()->for($this->competitionA)->groups()->create();
    Criterion::factory()->for($this->competitionA)->create();
    Judge::factory()->for($this->competitionA)->create();
    Participant::factory()->for($this->competitionA)->create();

    $this->actingAs($this->ownerA)->get(route('dashboard'))->assertOk()->assertSee($this->orgA->name);
    $this->actingAs($this->ownerA)->get(route('organizers.show', $this->orgA))->assertOk()->assertSee($this->competitionA->name);
    $this->actingAs($this->ownerA)->get(route('organizers.competitions.show', [$this->orgA, $this->competitionA]))->assertOk()->assertSee('Poules');
    $this->actingAs(User::factory()->platformAdmin()->create(), 'admin')->get(route('admin.organizers.index'))->assertOk()->assertSee($this->orgB->name);
});

it('starts a phase and runs its matches from the back-office', function () {
    Participant::factory()->for($this->competitionA)->count(4)->create();
    $phase = Phase::factory()->for($this->competitionA)->create(['rules' => ['vote_mode' => 'public']]);
    $staff = memberOf($this->orgA, OrganizerRole::Staff);

    $this->actingAs($staff)
        ->post(route('organizers.competitions.phases.start', [$this->orgA, $this->competitionA, $phase]))
        ->assertForbidden();

    $this->actingAs($this->ownerA)
        ->post(route('organizers.competitions.phases.start', [$this->orgA, $this->competitionA, $phase]))
        ->assertSessionHasNoErrors();

    $match = $phase->matches()->where('round', 1)->first();

    $this->actingAs($staff)
        ->post(route('organizers.competitions.matches.open-voting', [$this->orgA, $this->competitionA, $match]))
        ->assertSessionHasNoErrors();

    $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $match->slots()->value('participant_id')]);
    $vote->user_id = User::factory()->create()->id;
    $vote->save();

    $this->actingAs($staff)
        ->post(route('organizers.competitions.matches.close', [$this->orgA, $this->competitionA, $match]))
        ->assertSessionHasNoErrors();

    expect($match->fresh()->winner_id)->not->toBeNull()
        ->and($phase->matches()->where('round', 2)->first()->slots()->whereNotNull('participant_id')->count())->toBe(1);

    $this->actingAs($this->ownerA)
        ->get(route('organizers.competitions.show', [$this->orgA, $this->competitionA]))
        ->assertOk()
        ->assertSee('Clôturé');
});

it('does not resolve a match of another organizer', function () {
    $phaseB = Phase::factory()->for($this->competitionB)->create();
    $matchB = BattleMatch::factory()->create(['phase_id' => $phaseB->id]);

    $this->actingAs($this->ownerA)
        ->post("/organizers/{$this->orgA->slug}/competitions/{$this->competitionA->id}/matches/{$matchB->id}/close")
        ->assertNotFound();
});

it('reports flow errors to the organizer instead of crashing', function () {
    $phase = Phase::factory()->for($this->competitionA)->create();

    $this->actingAs($this->ownerA)
        ->post(route('organizers.competitions.phases.start', [$this->orgA, $this->competitionA, $phase]))
        ->assertSessionHasErrors('flow');
});
