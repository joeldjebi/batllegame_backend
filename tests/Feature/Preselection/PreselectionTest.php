<?php

use App\Enums\CompetitionStatus;
use App\Enums\JudgeStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PerformanceStatus;
use App\Enums\PhaseType;
use App\Enums\PreselectionState;
use App\Exceptions\CompetitionFlowException;
use App\Models\Criterion;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\PhaseLauncher;
use App\Services\PaymentService;
use App\Services\PreselectionService;
use App\Services\RegistrationService;
use Illuminate\Http\UploadedFile;
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
        'ends_at' => now()->addDay(), 'rules' => ['like_weight' => 70, 'jury_weight' => 70],
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

    expect(fn () => $service->publish($competition->preselection))->toThrow(CompetitionFlowException::class, 'délibération');

    $competition->preselection->update(['ends_at' => now()->subMinute()]);
    expect($service->publish($competition->preselection->fresh()))->toBe(2);

    // Finals: e1 = 54 + 10 = 64, e2 = 36 + 40 = 76, e3 = 48 + 0 = 48.
    expect($e2->fresh())->rank->toBe(1)->final_score->toBe(76.0)->selected->toBeTrue()
        ->and($e1->fresh())->rank->toBe(2)->final_score->toBe(64.0)->selected->toBeTrue()
        ->and($e3->fresh())->rank->toBe(3)->selected->toBeFalse()
        ->and($artists->map->fresh()->pluck('status')->all())->toBe([ParticipantStatus::Validated, ParticipantStatus::Validated, ParticipantStatus::NotSelected])
        ->and($competition->preselection->fresh()->state())->toBe(PreselectionState::Published);
});

it('publishes after the deliberation with the scores received: an unscored entry counts 0 for the jury', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judge = $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);
    [$scored, $forgotten] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $service = app(PreselectionService::class);
    $service->score($judge, $scored, ['scores' => [['criterion_id' => $criterion->id, 'score' => 5]]]);
    $competition->preselection->update(['ends_at' => now()->subMinute()]);

    expect($service->unscoredCount($competition->preselection->fresh()))->toBe(1)
        ->and($service->publish($competition->preselection->fresh()))->toBe(2)
        ->and($forgotten->fresh()->jury_score)->toBeNull()
        ->and($scored->fresh()->rank)->toBe(1);
});

it('follows the organizer timeline: submissions, then public vote, then jury deliberation', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judge = $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);
    $entry = preselectionEntry($artists[0]);
    $service = app(PreselectionService::class);
    $preselection = $competition->preselection;
    $preselection->update(['ends_at' => now()->addHour(), 'vote_ends_at' => now()->addHours(3), 'deliberation_hours' => 24]);
    $likeUrl = "/api/competitions/{$competition->slug}/preselection/entries/{$entry->id}/like";
    $scoreUrl = route('jury.competitions.preselection.scores.store', [$competition, $entry]);
    $score = ['scores' => [['criterion_id' => $criterion->id, 'score' => 7]]];

    // Submissions open: likes open too.
    expect($preselection->fresh()->state())->toBe(PreselectionState::Open);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($likeUrl)->assertCreated();

    // Public vote: no new submission, likes still allowed, jury scores.
    $this->travel(2)->hours();
    expect($preselection->fresh()->state())->toBe(PreselectionState::Voting)
        ->and(fn () => preselectionEntry($artists[1]))->toThrow(CompetitionFlowException::class);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($likeUrl)->assertCreated();

    // Deliberation: likes closed, the jury still scores, publication not yet possible.
    $this->travel(2)->hours();
    expect($preselection->fresh()->state())->toBe(PreselectionState::Deliberation);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($likeUrl)->assertForbidden();
    $this->actingAs($judge->user, 'jury')->post($scoreUrl, $score)->assertSessionHasNoErrors();
    expect(fn () => $service->publish($preselection->fresh()))->toThrow(CompetitionFlowException::class, 'délibération');

    // Deliberation over: scores locked, the organizer publishes.
    $this->travel(1)->days();
    expect($preselection->fresh()->state())->toBe(PreselectionState::Closed)
        ->and($judge->user->can('score', $entry->fresh()))->toBeFalse()
        ->and($service->publish($preselection->fresh()))->toBe(1)
        ->and($entry->fresh()->likes_count)->toBe(2);
});

it('closes likes and ranks 100 % jury when the organizer disabled the public vote', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2, ['like_weight' => 50, 'jury_weight' => 50], ['submissions_require_approval' => false, 'public_voting_enabled' => false]);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judge = $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);
    [$e1, $e2] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/competitions/{$competition->slug}/preselection/entries/{$e1->id}/like")
        ->assertForbidden();

    $service = app(PreselectionService::class);
    $service->score($judge, $e1, ['scores' => [['criterion_id' => $criterion->id, 'score' => 4]]]);
    $service->score($judge, $e2, ['scores' => [['criterion_id' => $criterion->id, 'score' => 8]]]);
    $service->rank($competition->preselection->fresh());

    expect($e2->fresh())->final_score->toBe(80.0)->rank->toBe(1)
        ->and($competition->preselection->fresh()->effectiveWeights())->toBe(['jury' => 100, 'likes' => 0]);
});

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
        'ends_at' => now()->addDays(8)->format('Y-m-d H:i'),
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

    $this->actingAs($artists[1]->user, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('À faire maintenant')->assertSee('Envoyer ma prestation');

    $this->actingAs($judge, 'jury')->get(route('jury.competitions.preselection', $competition))->assertOk()->assertSee('Commencer la notation')->assertSee($entry->participant->stage_name);
    $this->actingAs($judge, 'jury')->get(route('jury.competitions.preselection.entries.show', [$competition, $entry]))->assertOk()->assertSee('Flow');

    $this->getJson("/api/competitions/{$competition->slug}/preselection")->assertOk()->assertJsonPath('data.state', 'ouverte')->assertJsonPath('data.entries_count', 1);
    $this->getJson("/api/competitions/{$competition->slug}/preselection/entries")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $entry->id);
});

it('freezes the rules once a performance is sent but keeps dates editable', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1, ['selection_size' => 4]);
    $newEnd = now()->addDays(3)->startOfMinute();

    app(PreselectionService::class)->configure($competition, ['ends_at' => now()->addDays(2), 'rules' => ['selection_size' => 6]]);
    expect($competition->preselection->fresh()->rules->selectionSize)->toBe(6);

    preselectionEntry($artists[0]);
    app(PreselectionService::class)->configure($competition, ['ends_at' => $newEnd, 'rules' => ['selection_size' => 20]]);

    expect($competition->preselection->fresh())
        ->rules->selectionSize->toBe(6)
        ->ends_at->equalTo($newEnd)->toBeTrue();
});

it('shows the payment pitch instead of any upload while the fee is unpaid', function () {
    ['competition' => $competition] = competitionWithPreselection(0, fee: 5000);
    $artist = User::factory()->withAvatar()->create();
    Participant::factory()->for($competition)->create(['user_id' => $artist->id, 'status' => ParticipantStatus::PaymentPending]);

    $this->actingAs($artist, 'member')->get(route('artist.dashboard'))
        ->assertOk()
        ->assertSee("Plus qu'un pas pour monter sur scène", false)
        ->assertSee(route('artist.competitions.payment', $competition))
        ->assertDontSee(route('artist.competitions.preselection.submit', $competition))
        ->assertDontSee('name="media"', false);
});

it('refuses a submission from an artist who has not paid, whatever the status', function () {
    ['competition' => $competition] = competitionWithPreselection(0, fee: 5000);
    $artist = Participant::factory()->for($competition)->create(['status' => ParticipantStatus::Registered]);

    expect(fn () => preselectionEntry($artist))->toThrow(CompetitionFlowException::class, 'frais payés');

    $artist->update(['status' => ParticipantStatus::PaymentPending]);
    app(PaymentService::class)->simulate($artist, PaymentMethod::Wave);
    expect(preselectionEntry($artist->fresh())->status)->toBe(PerformanceStatus::Approved);
});

it('unlocks the upload once the fee is paid', function () {
    ['competition' => $competition] = competitionWithPreselection(0, fee: 5000);
    $artist = User::factory()->withAvatar()->create();
    $participant = Participant::factory()->for($competition)->create(['user_id' => $artist->id, 'status' => ParticipantStatus::PaymentPending]);

    app(PaymentService::class)->simulate($participant, PaymentMethod::OrangeMoney);

    $this->actingAs($artist, 'member')->get(route('artist.dashboard'))
        ->assertOk()
        ->assertSee(route('artist.competitions.preselection.submit', $competition))
        ->assertDontSee("Plus qu'un pas pour monter sur scène", false);
});

it('never validates nor ranks the entry of an artist who has not paid', function () {
    ['competition' => $competition, 'owner' => $owner, 'organizer' => $organizer, 'artists' => $artists] = competitionWithPreselection(2, settings: ['submissions_require_approval' => true], fee: 5000);
    $artists->each(fn ($artist) => app(PaymentService::class)->simulate($artist->forceFill(['status' => ParticipantStatus::PaymentPending]), PaymentMethod::Wave));
    [$paid, $refunded] = $artists->map(fn ($artist) => preselectionEntry($artist->fresh()))->all();
    // The second payment is voided afterwards (refund, fake demo payment…).
    $refunded->participant->payments()->delete();
    $review = fn ($entry) => route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]);

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))
        ->assertSee('Frais non payés')->assertSee('validation possible après paiement')->assertSee('2 prestation(s) à valider');

    $this->actingAs($owner, 'web')->patch($review($refunded), ['decision' => 'approve'])->assertSessionHasErrors('flow');
    $this->actingAs($owner, 'web')->patch($review($paid), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($refunded->fresh()->status)->toBe(PerformanceStatus::Pending);

    // Even an entry approved before the payment was voided stays out of the ranking.
    $refunded->forceFill(['status' => PerformanceStatus::Approved])->save();
    app(PreselectionService::class)->rank($competition->preselection->fresh());

    expect($paid->fresh()->rank)->toBe(1)->and($refunded->fresh()->rank)->toBeNull();
});

it('lists entries in an airy ranking with a detail modal to watch and review each one', function () {
    ['competition' => $competition, 'owner' => $owner, 'organizer' => $organizer, 'artists' => $artists] = competitionWithPreselection(2, settings: ['submissions_require_approval' => true]);
    $artists->each(fn ($artist) => preselectionEntry($artist));

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))
        ->assertOk()
        ->assertSee('Rechercher un artiste')
        ->assertSeeInOrder(['Toutes', 'À valider', 'Validées', 'Rejetées'])
        ->assertSee("\$dispatch('open-modal', 'entry-", false)
        ->assertSee('Valider la prestation')
        ->assertSee('Provenance du fichier')
        ->assertSee("Motif (visible par l'artiste)", false)
        ->assertSee('Configuration de la présélection')
        ->assertDontSee('@js(', false);
});

it('likes, moves and removes a like in AJAX with the counters of every entry', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2, settings: ['submissions_require_approval' => false, 'show_live_results' => true]);
    [$a, $b] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $fan = User::factory()->create();
    $like = fn ($entry) => $this->actingAs($fan, 'member')->postJson(route('fan.competitions.preselection.like', [$competition, $entry]));

    $like($a)->assertOk()
        ->assertJsonPath('my_like', $a->id)
        ->assertJsonPath("counts.{$a->id}", 1)
        ->assertJsonPath('message', 'Vous soutenez Artiste 1 !');

    // One like per competition: moving it frees the first entry.
    $like($b)->assertOk()->assertJsonPath('my_like', $b->id)->assertJsonPath("counts.{$a->id}", 0)->assertJsonPath("counts.{$b->id}", 1);

    $this->actingAs($fan, 'member')->deleteJson(route('fan.competitions.preselection.unlike', $competition))
        ->assertOk()->assertJsonPath('my_like', null)->assertJsonPath("counts.{$b->id}", 0);

    // Refusals come back as JSON messages (own entry).
    $this->actingAs($artists[0]->user, 'member')->postJson(route('fan.competitions.preselection.like', [$competition, $a]))
        ->assertForbidden()->assertJsonPath('message', 'Vous ne pouvez pas liker votre propre prestation.');
});

it('shows the counters to fans once they liked, hides them from the others, and renders the like component', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $entry = preselectionEntry($artists[0]);
    [$fan, $other] = User::factory()->count(2)->create();

    // Like a poll: the counters appear with the fan's own like.
    $this->actingAs($fan, 'member')->postJson(route('fan.competitions.preselection.like', [$competition, $entry]))
        ->assertOk()->assertJsonPath('my_like', $entry->id)->assertJsonPath("counts.{$entry->id}", 1);

    $this->actingAs($fan, 'member')->get(route('fan.competitions.show', $competition))
        ->assertOk()
        ->assertSee('x-data="preselectionLikes(', false)
        ->assertSee('"my_like":'.$entry->id, false)
        ->assertSee('1 like')
        ->assertSee("Je n'aime plus")
        ->assertDontSee('@js(', false);

    // Someone who has not liked (and no live results) does not see them.
    $this->actingAs($other, 'member')->get(route('fan.competitions.show', $competition))
        ->assertOk()->assertSee('"counts":null', false)->assertDontSee('1 like');
});

it('has a shareable page per entry with link previews and share buttons', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    [$entry] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $url = route('fan.competitions.preselection.entry', [$competition, $entry]);

    $this->get($url)
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Artiste 1 · '.e($competition->name).'">', false)
        ->assertSee('og-default.png', false)
        ->assertSee('Partager la prestation')
        ->assertSee('https://wa.me/?text=', false)
        ->assertSee('Voir les 1 autre(s) prestation(s)');

    // Unpublished entries and foreign competitions stay hidden.
    $entry->forceFill(['status' => PerformanceStatus::Pending])->save();
    $this->get($url)->assertNotFound();
    ['competition' => $other] = competitionWithPreselection(0);
    $this->get(route('fan.competitions.preselection.entry', [$other, $entry]))->assertNotFound();
});

it('lets an artist send the performance once the organizer validated the registration', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(1, settings: ['registration_requires_approval' => true]);
    $artist = $artists[0];

    expect(fn () => preselectionEntry($artist))->toThrow(CompetitionFlowException::class, "attend la validation de l'organisateur");
    $this->actingAs($artist->user, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('Inscription en attente de validation', false);

    $this->actingAs($owner, 'web')->patch(route('organizers.competitions.participants.update', [$organizer, $competition, $artist]), ['status' => ParticipantStatus::Validated->value])->assertRedirect();

    expect(preselectionEntry($artist->fresh())->participant_id)->toBe($artist->id);
});

it('marks validated artists who were not selected as not selected', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(3, rules: ['selection_size' => 2, 'like_weight' => 100, 'jury_weight' => 0]);
    $artists->each(fn ($a) => $a->update(['status' => ParticipantStatus::Validated]));
    $entries = $artists->map(fn ($a) => preselectionEntry($a->fresh()));
    likeAs($entries[0]);
    likeAs($entries[0]);
    likeAs($entries[1]);
    $competition->preselection->update(['ends_at' => now()->subMinute(), 'vote_ends_at' => now()->subMinute(), 'deliberation_hours' => 0]);

    app(PreselectionService::class)->publish($competition->preselection->fresh());

    expect($artists[2]->fresh()->status)->toBe(ParticipantStatus::NotSelected)
        ->and($artists[0]->fresh()->status)->toBe(ParticipantStatus::Validated);
});

it('opens the submissions as soon as the pre-selection exists: only the deadline matters', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $competition->preselection->update(['ends_at' => now()->addDays(5)]);

    expect($competition->preselection->fresh()->state())->toBe(PreselectionState::Open)
        ->and(preselectionEntry($artists[0])->participant_id)->toBe($artists[0]->id);

    $competition->preselection->update(['ends_at' => now()->subMinute(), 'vote_ends_at' => now()->addDay()]);
    expect(fn () => preselectionEntry($artists[0]->fresh()))->toThrow(CompetitionFlowException::class);
});

it('paginates the participants and the pre-selection entries by 15', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(17);
    $artists->each(fn ($artist) => preselectionEntry($artist));

    $html = $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertOk()->getContent();

    // Participants: in the page (filters in the browser); entries: 20 per page from the server.
    expect(substr_count($html, 'aria-label="Pagination"'))->toBe(1)
        ->and($html)->toContain('x-show="visible(16)"');

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]).'?pre_q='.urlencode($artists[3]->stage_name))
        ->assertOk()->assertSee('Voir la fiche');
});

it('reviews an entry in AJAX', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(2, settings: ['submissions_require_approval' => true]);
    [$a, $b] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $url = fn ($entry) => route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]);
    expect($this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->getContent())->toContain('x-data="ajaxForm');

    $this->actingAs($owner, 'web')->patchJson($url($a), ['decision' => 'approve'])->assertOk()
        ->assertJsonPath('status', PerformanceStatus::Approved->value)->assertJsonPath('message', fn ($m) => str_contains($m, 'validée'));
    $this->actingAs($owner, 'web')->patchJson($url($b), ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->actingAs($owner, 'web')->patchJson($url($b), ['decision' => 'reject', 'reason' => 'Vidéo TikTok'])->assertOk()
        ->assertJsonPath('status', PerformanceStatus::Rejected->value);
});

it('updates the jury score and the ranking as soon as a judge scores', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judge = $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);
    $entry = preselectionEntry($artists[0]);

    app(PreselectionService::class)->score($judge, $entry, ['scores' => [['criterion_id' => $criterion->id, 'score' => 8]]]);

    expect($entry->fresh())->jury_score->toBe(80.0)->rank->toBe(1);
});

it('requires a profile photo to send the pre-selection entry, in the app and on the web', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $artist = $artists[0];
    $artist->user->forceFill(['avatar_path' => null])->save();
    $file = fn () => UploadedFile::fake()->create('take.mp4', 300, 'video/mp4');

    $this->actingAs($artist->user, 'sanctum')
        ->postJson(route('api.competitions.preselection.submit', $competition), ['media' => $file()])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'avatar_required');

    $this->actingAs($artist->user, 'member')
        ->post(route('artist.competitions.preselection.submit', $competition), ['media' => $file()])
        ->assertSessionHasErrors(['flow' => 'Ajoutez une photo de profil pour envoyer votre prestation : elle vous représente auprès du public et du jury.']);

    expect($artist->fresh()->preselectionEntry)->toBeNull();

    // The artist area asks for the photo instead of showing the upload.
    $this->actingAs($artist->user->fresh(), 'member')->get(route('artist.dashboard'))->assertOk()
        ->assertSee('Ajoute ta photo de profil pour envoyer ta prestation')
        ->assertDontSee(route('artist.competitions.preselection.submit', $competition));
});
