<?php

use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\OrganizerRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Criterion;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Phase;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->index = route('organizers.competitions.index', $this->organizer);
});

it('lists only the organizer competitions, with search, status, discipline and sort', function () {
    Competition::factory()->for($this->organizer)->create(['name' => 'Yopougon Rap Arena', 'discipline' => Discipline::Rap]);
    Competition::factory()->for($this->organizer)->draft()->create(['name' => 'Slam de Bouaké', 'discipline' => Discipline::Slam]);
    Competition::factory()->create(['name' => 'Battle concurrente']);

    $this->actingAs($this->owner, 'web')->get($this->index)
        ->assertOk()->assertSee('Yopougon Rap Arena')->assertSee('Slam de Bouaké')->assertDontSee('Battle concurrente')
        ->assertSee('Nouvelle compétition');

    $this->actingAs($this->owner, 'web')->get($this->index.'?q=bouak')->assertSee('Slam de Bouaké')->assertDontSee('Yopougon Rap Arena');
    $this->actingAs($this->owner, 'web')->get($this->index.'?status=brouillon')->assertSee('Slam de Bouaké')->assertDontSee('Yopougon Rap Arena');
    $this->actingAs($this->owner, 'web')->get($this->index.'?discipline=rap')->assertSee('Yopougon Rap Arena')->assertDontSee('Slam de Bouaké');
    $this->actingAs($this->owner, 'web')->get($this->index.'?sort=name')->assertSeeInOrder(['Slam de Bouaké', 'Yopougon Rap Arena']);
    $this->actingAs($this->owner, 'web')->get($this->index.'?sort=pirate')->assertSessionHasErrors('sort');
});

it('hides the list of another organizer behind a 404', function () {
    $this->actingAs(User::factory()->create(), 'web')->get($this->index)->assertNotFound();
});

it('lets staff see the list without write actions', function () {
    $staff = User::factory()->create();
    $this->organizer->users()->attach($staff, ['role' => OrganizerRole::Staff]);
    Competition::factory()->for($this->organizer)->draft()->create(['name' => 'Session staff']);

    $this->actingAs($staff, 'web')->get($this->index)
        ->assertOk()->assertSee('Session staff')->assertDontSee('Nouvelle compétition')->assertDontSee('Dupliquer');
});

it('runs the full CRUD: create, read, update, delete', function () {
    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.store', $this->organizer), [
        'name' => 'Freestyle Cocody', 'discipline' => 'freestyle', 'mode' => 'en_ligne', 'max_participants' => 16, 'entry_fee' => 2000,
        'description' => '<div>Le <strong>freestyle</strong> de Cocody</div>',
    ])->assertRedirect();
    $competition = $this->organizer->competitions()->where('name', 'Freestyle Cocody')->firstOrFail();
    expect($competition->status)->toBe(CompetitionStatus::Draft);

    $this->actingAs($this->owner, 'web')->get($this->index)->assertSee('Freestyle Cocody')->assertSee('2 000 XOF');

    $this->actingAs($this->owner, 'web')->put(route('organizers.competitions.update', [$this->organizer, $competition]), ['name' => 'Freestyle Cocody 2027', 'max_participants' => 32])
        ->assertSessionHasNoErrors();
    expect($competition->fresh())->name->toBe('Freestyle Cocody 2027')->max_participants->toBe(32);

    $this->actingAs($this->owner, 'web')->delete(route('organizers.competitions.destroy', [$this->organizer, $competition]))
        ->assertRedirect($this->index);
    expect(Competition::find($competition->id))->toBeNull()
        ->and(Competition::withTrashed()->find($competition->id))->not->toBeNull();
});

it('deletes any competition until a participant has paid, then only lets it be cancelled', function () {
    $open = Competition::factory()->for($this->organizer)->create(['status' => CompetitionStatus::Registration, 'entry_fee' => 5000]);
    $unpaid = Participant::factory()->for($open)->create();

    // Registered but nobody paid: the owner can delete it.
    $this->actingAs($this->owner, 'web')->get($this->index)->assertSee('1 inscrit(s) seront retirés', false);
    $this->actingAs($this->owner, 'web')->delete(route('organizers.competitions.destroy', [$this->organizer, $open]))->assertRedirect($this->index);
    expect(Competition::find($open->id))->toBeNull();

    $paid = Competition::factory()->for($this->organizer)->create(['status' => CompetitionStatus::InProgress, 'entry_fee' => 5000]);
    $participant = Participant::factory()->for($paid)->create();
    $payment = new Payment;
    $payment->forceFill([
        'competition_id' => $paid->id, 'participant_id' => $participant->id, 'user_id' => $participant->user_id, 'amount' => 5000, 'currency' => 'XOF',
        'method' => PaymentMethod::Wave, 'provider' => 'simulation', 'reference' => 'BG-TEST1', 'status' => PaymentStatus::Paid, 'paid_at' => now(),
    ])->save();

    $this->actingAs($this->owner, 'web')->delete(route('organizers.competitions.destroy', [$this->organizer, $paid]))->assertForbidden();
    $this->actingAs($this->owner, 'web')->get($this->index)->assertSee('des participants ont déjà payé', false);
    expect($paid->fresh())->not->toBeNull();
});

it('duplicates a competition as a draft with its presentation, criteria and phases only', function () {
    $source = Competition::factory()->for($this->organizer)->create(['name' => 'Voix d\'Or', 'entry_fee' => 5000]);
    Criterion::factory()->for($source)->create(['name' => 'Justesse', 'max_points' => 10]);
    Phase::factory()->for($source)->create(['type' => PhaseType::SingleElimination, 'position' => 1]);
    Participant::factory()->for($source)->count(2)->create();

    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.duplicate', [$this->organizer, $source]))->assertRedirect();

    $copy = $this->organizer->competitions()->where('name', "Voix d'Or (copie)")->firstOrFail();
    expect($copy)
        ->status->toBe(CompetitionStatus::Draft)
        ->entry_fee->toBe(5000)
        ->description->toBe($source->description)
        ->prizes->toBe($source->prizes)
        ->slug->not->toBe($source->slug)
        ->and($copy->criteria()->pluck('name')->all())->toBe(['Justesse'])
        ->and($copy->phases()->count())->toBe(1)
        ->and($copy->phases()->first()->status)->toBe(PhaseStatus::Pending)
        ->and($copy->participants()->count())->toBe(0)
        ->and($copy->creator_id ?? $copy->created_by)->toBe($this->owner->id);
});

it('never duplicates a competition of another organizer', function () {
    $foreign = Competition::factory()->create();

    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.duplicate', [$this->organizer, $foreign]))->assertNotFound();
});
