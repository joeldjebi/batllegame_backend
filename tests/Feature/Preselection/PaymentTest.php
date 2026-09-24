<?php

use App\Enums\CompetitionStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PreselectionService;
use App\Services\RegistrationService;

beforeEach(function () {
    Country::factory()->ivoryCoast();
    $this->competition = Competition::factory()->create(['status' => CompetitionStatus::Registration, 'entry_fee' => 5000, 'settings' => ['registration_requires_approval' => false]]);
    $this->artist = User::factory()->create();
});

it('keeps a paid registration pending until the fee is paid', function () {
    $participant = app(RegistrationService::class)->register($this->artist, $this->competition, 'MC Pay');

    expect($participant->status)->toBe(ParticipantStatus::PaymentPending);

    $payment = app(PaymentService::class)->simulate($participant, PaymentMethod::Wave);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount)->toBe(5000)
        ->and($payment->reference)->toStartWith('BG-')
        ->and($participant->fresh()->status)->toBe(ParticipantStatus::Validated);

    expect(fn () => app(PaymentService::class)->simulate($participant->fresh(), PaymentMethod::Wave))->toThrow(CompetitionFlowException::class);
});

it('records a failed simulated payment and keeps the registration pending', function () {
    $participant = app(RegistrationService::class)->register($this->artist, $this->competition, 'MC Pay');

    $payment = app(PaymentService::class)->simulate($participant, PaymentMethod::OrangeMoney, succeeds: false);

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($participant->fresh()->status)->toBe(ParticipantStatus::PaymentPending);
});

it('does not ask free competitions for a payment', function () {
    $free = Competition::factory()->create(['status' => CompetitionStatus::Registration, 'entry_fee' => 0, 'settings' => ['registration_requires_approval' => false]]);

    expect(app(RegistrationService::class)->register($this->artist, $free, 'MC Free')->status)->toBe(ParticipantStatus::Validated);
});

it('sends artists to the simulated checkout from the web portal', function () {
    $this->actingAs($this->artist, 'member')
        ->post(route('artist.competitions.register', $this->competition), ['stage_name' => 'MC Web'])
        ->assertRedirect(route('artist.competitions.payment', $this->competition));

    $this->actingAs($this->artist, 'member')->get(route('artist.competitions.payment', $this->competition))->assertOk()->assertSee('Paiement simulé')->assertSee('5 000');

    $this->actingAs($this->artist, 'member')
        ->post(route('artist.competitions.payment', $this->competition), ['method' => 'mtn_momo', 'outcome' => 'echec'])
        ->assertSessionHasErrors('payment');

    $this->actingAs($this->artist, 'member')
        ->post(route('artist.competitions.payment', $this->competition), ['method' => 'mtn_momo', 'outcome' => 'succes'])
        ->assertRedirect(route('artist.dashboard'));

    expect(Payment::query()->where('status', PaymentStatus::Paid)->count())->toBe(1)
        ->and($this->artist->participations()->first()->status)->toBe(ParticipantStatus::Validated);
});

it('pays through the API', function () {
    $this->actingAs($this->artist, 'sanctum')
        ->postJson("/api/competitions/{$this->competition->slug}/registrations", ['stage_name' => 'MC Api'])
        ->assertCreated()
        ->assertJsonPath('payment_required', true)
        ->assertJsonPath('status', 'paiement_en_attente');

    $this->actingAs($this->artist, 'sanctum')
        ->postJson("/api/competitions/{$this->competition->slug}/payment", ['method' => 'carte', 'simulate_failure' => true])
        ->assertStatus(402);

    $this->actingAs($this->artist, 'sanctum')
        ->postJson("/api/competitions/{$this->competition->slug}/payment", ['method' => 'carte'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'payee')
        ->assertJsonPath('data.participant_status', 'valide');
});

it('treats a registration made before the fee as unpaid: pitch instead of upload, and it can pay', function () {
    app(PreselectionService::class)->configure($this->competition, ['ends_at' => now()->addDays(3),
        'rules' => ['like_weight' => 40, 'jury_weight' => 60, 'selection_size' => 2],
    ]);
    $participant = $this->competition->participants()->create(['user_id' => $this->artist->id, 'stage_name' => 'MC Legacy', 'status' => ParticipantStatus::Registered]);

    $this->actingAs($this->artist, 'member')->get(route('artist.dashboard'))
        ->assertOk()
        ->assertSee('Plus qu', false)
        ->assertDontSee('Envoyer ma prestation');

    $this->actingAs($this->artist, 'member')->get(route('artist.competitions.payment', $this->competition))->assertOk();

    app(PaymentService::class)->simulate($participant, PaymentMethod::Wave);

    expect($participant->fresh()->hasPaid())->toBeTrue()
        ->and($participant->fresh()->status)->toBe(ParticipantStatus::Registered);
});
