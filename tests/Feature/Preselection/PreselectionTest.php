<?php

use App\Enums\CompetitionStatus;
use App\Enums\JudgeStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PhaseType;
use App\Enums\PreselectionState;
use App\Exceptions\CompetitionFlowException;
use App\Models\Criterion;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\PhaseLauncher;
use App\Services\PreselectionService;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('validates the pre-selection rules', function () {
    ['competition' => $competition] = competitionWithPreselection();
    $competition->preselection()->delete();
    $competition->unsetRelation('preselection');

    expect(fn () => app(PreselectionService::class)->configure($competition, [
        'starts_at' => now(), 'ends_at' => now()->addDay(), 'rules' => ['like_weight' => 70, 'jury_weight' => 70],
    ]))->toThrow(ValidationException::class);
});

it('keeps paid artists only and a new registration in « inscrit » with a pre-selection', function () {
    ['competition' => $competition] = competitionWithPreselection(0);
    $competition->update(['entry_fee' => 0]);

    $participant = app(RegistrationService::class)->register(User::factory()->create(), $competition, 'MC Candidat');

    expect($participant->status)->toBe(ParticipantStatus::Registered);

    $unpaid = Participant::factory()->for($competition)->create(['status' => ParticipantStatus::PaymentPending]);
    expect(fn () => preselectionEntry($unpaid))->toThrow(CompetitionFlowException::class, 'frais payés');
});

it('accepts one entry per artist, replaced by a new upload', function () {
    ['artists' => $artists] = competitionWithPreselection(1);

    preselectionEntry($artists[0]);
    $entry = preselectionEntry($artists[0]);

    expect($artists[0]->preselectionEntry()->count())->toBe(1)
        ->and($entry->status)->toBe(PerformanceStatus::Approved)
        ->and($entry->duration_seconds)->toBe(60);
});

it('refuses entries outside the period', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $competition->preselection->update(['ends_at' => now()->subMinute()]);

    expect(fn () => preselectionEntry($artists[0]))->toThrow(CompetitionFlowException::class, "n'est pas ouverte");
});

it('allows one like per user and competition, and moving it', function () {
    ['artists' => $artists] = competitionWithPreselection(2);
    [$a, $b] = [preselectionEntry($artists[0]), preselectionEntry($artists[1])];

    $fan = likeAs($a);
    expect($a->fresh()->likes_count)->toBe(1);

    likeAs($b, $fan);

    expect($a->fresh()->likes_count)->toBe(0)
        ->and($b->fresh()->likes_count)->toBe(1)
        ->and($a->preselection->likes()->where('user_id', $fan->id)->count())->toBe(1);
});

it('protects likes: verified phone, not own entry, not a judge, open period', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    $entry = preselectionEntry($artists[0]);
    $url = "/api/competitions/{$competition->slug}/preselection/entries/{$entry->id}/like";

    $this->actingAs(User::factory()->unverified()->create(), 'sanctum')->postJson($url)->assertForbidden();
    $this->actingAs($artists[0]->user, 'sanctum')->postJson($url)->assertForbidden();

    $judge = User::factory()->create();
    $competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    $this->actingAs($judge, 'sanctum')->postJson($url)->assertForbidden();

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url)->assertCreated()->assertJsonPath('likes', 1);

    $competition->preselection->update(['ends_at' => now()->subMinute()]);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url)->assertForbidden();
});

it('never resolves an entry through another competition', function () {
    ['artists' => $artists] = competitionWithPreselection(1);
    ['competition' => $other] = competitionWithPreselection(0);
    $entry = preselectionEntry($artists[0]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/competitions/{$other->slug}/preselection/entries/{$entry->id}/like")
        ->assertNotFound();
});

it('ranks entries with the jury and like weights, then publishes the selection', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(3, ['like_weight' => 40, 'jury_weight' => 60, 'selection_size' => 2]);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judge = $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);
    [$e1, $e2, $e3] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $service = app(PreselectionService::class);

    // Jury: 9, 6, 8 -> 90, 60, 80. Likes: 1, 4, 0 -> 25, 100, 0.
    foreach ([[$e1, 9], [$e2, 6], [$e3, 8]] as [$entry, $score]) {
        $service->score($judge, $entry, ['scores' => [['criterion_id' => $criterion->id, 'score' => $score]]]);
    }
    likeAs($e1);
    foreach (range(1, 4) as $i) {
        likeAs($e2);
    }

    expect(fn () => $service->publish($competition->preselection))->toThrow(CompetitionFlowException::class, "n'est pas terminée");

    $competition->preselection->update(['ends_at' => now()->subMinute()]);
    expect($service->publish($competition->preselection->fresh()))->toBe(2);

    // Finals: e1 = 54 + 10 = 64, e2 = 36 + 40 = 76, e3 = 48 + 0 = 48.
    expect($e2->fresh())->rank->toBe(1)->final_score->toBe(76.0)->selected->toBeTrue()
        ->and($e1->fresh())->rank->toBe(2)->final_score->toBe(64.0)->selected->toBeTrue()
        ->and($e3->fresh())->rank->toBe(3)->selected->toBeFalse()
        ->and($artists->map->fresh()->pluck('status')->all())->toBe([ParticipantStatus::Validated, ParticipantStatus::Validated, ParticipantStatus::NotSelected])
        ->and($competition->preselection->fresh()->state())->toBe(PreselectionState::Published);
});

it('requires the jury to score every entry before publishing', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    preselectionEntry($artists[0]);
    $competition->preselection->update(['ends_at' => now()->subMinute()]);

    app(PreselectionService::class)->publish($competition->preselection->fresh());
})->throws(CompetitionFlowException::class, 'jury');

it('blocks the first phase until the selection is published, then only selected artists compete', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(3, ['like_weight' => 100, 'jury_weight' => 0]);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));
    likeAs($entries[0]);
    likeAs($entries[1]);
    likeAs($entries[1]);
    $phase = Phase::factory()->for($competition)->create(['type' => PhaseType::SingleElimination, 'rules' => ['vote_mode' => 'public']]);

    expect(fn () => app(PhaseLauncher::class)->start($phase))->toThrow(CompetitionFlowException::class, 'Publiez');

    $competition->preselection->update(['ends_at' => now()->subMinute()]);
    app(PreselectionService::class)->publish($competition->preselection->fresh());
    app(PhaseLauncher::class)->start($phase->fresh());

    $players = $phase->matches()->first()->slots()->pluck('participant_id')->sort()->values()->all();
    expect($players)->toBe(collect([$artists[0]->id, $artists[1]->id])->sort()->values()->all());
});

it('lets the organizer configure, review and publish from the back-office', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(1, ['like_weight' => 100, 'jury_weight' => 0], ['submissions_require_approval' => true]);
    $entry = preselectionEntry($artists[0]);

    expect($entry->status)->toBe(PerformanceStatus::Pending);

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertOk()->assertSee('Présélection')->assertSee('Artiste 1');

    $this->actingAs($owner, 'web')
        ->patch(route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]), ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $competition->preselection->update(['ends_at' => now()->subMinute()]);
    $competition->update(['status' => CompetitionStatus::Registration]);
    $this->actingAs($owner, 'web')->post(route('organizers.competitions.preselection.publish', [$organizer, $competition]))->assertSessionHasNoErrors();

    expect($artists[0]->fresh()->status)->toBe(ParticipantStatus::Validated);
});

it('creates the pre-selection from the back-office form', function () {
    ['competition' => $competition, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(0);
    $competition->preselection()->delete();

    $this->actingAs($owner, 'web')->put(route('organizers.competitions.preselection.update', [$organizer, $competition]), [
        'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'ends_at' => now()->addDays(8)->format('Y-m-d H:i'),
        'rules' => ['like_weight' => 30, 'jury_weight' => 70, 'selection_size' => 8, 'media_types' => ['video'], 'media_max_duration' => 120, 'media_max_size_mb' => 50],
    ])->assertSessionHasNoErrors();

    expect($competition->fresh()->preselection->rules)->selectionSize->toBe(8)->likeWeight->toBe(30);
});

it('shows the pre-selection to the public, the artists and the judges', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    $entry = preselectionEntry($artists[0]);
    $fan = User::factory()->create();
    $judge = User::factory()->create();
    $competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    Criterion::factory()->for($competition)->create(['name' => 'Flow']);

    $this->actingAs($fan, 'member')->get(route('fan.competitions.show', $competition))->assertOk()->assertSee('un seul like par compétition', false);
    $this->actingAs($fan, 'member')->post(route('fan.competitions.preselection.like', [$competition, $entry]))->assertSessionHasNoErrors();
    expect($entry->fresh()->likes_count)->toBe(1);

    $this->actingAs($artists[1]->user, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('Envoyer ma prestation de présélection');

    $this->actingAs($judge, 'jury')->get(route('jury.competitions.preselection', $competition))->assertOk()->assertSee('Flow');

    $this->getJson("/api/competitions/{$competition->slug}/preselection")->assertOk()->assertJsonPath('data.state', 'ouverte')->assertJsonCount(1, 'data.entries');
});

it('freezes the rules once the pre-selection has started but keeps dates editable', function () {
    ['competition' => $competition] = competitionWithPreselection(0, ['selection_size' => 4]);
    $newEnd = now()->addDays(3)->startOfMinute();

    app(PreselectionService::class)->configure($competition, ['starts_at' => now()->subHour(), 'ends_at' => $newEnd, 'rules' => ['selection_size' => 20]]);

    expect($competition->preselection->fresh())
        ->rules->selectionSize->toBe(4)
        ->ends_at->equalTo($newEnd)->toBeTrue();
});
