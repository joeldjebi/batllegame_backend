<?php

use App\Enums\JudgeStatus;
use App\Enums\MatchStatus;
use App\Enums\OrganizerRole;
use App\Enums\PaymentMethod;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Realtime\Channel;
use App\Realtime\Realtime;
use App\Realtime\RealtimeToken;
use App\Services\PaymentService;
use App\Services\RegistrationService;
use App\Services\SubmissionService;
use App\Services\VotingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

/** Channels granted by a token, verified like realtime/server.js does. */
function tokenChannels(?string $token): array
{
    if ($token === null) {
        return [];
    }
    [$payload, $signature] = explode('.', $token);
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, config('realtime.secret'), true)), '+/', '-_'), '=');
    expect(hash_equals($expected, $signature))->toBeTrue();

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['c'];
}

beforeEach(function () {
    $this->realtime = app(Realtime::class)->fake();
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
    $this->competition = Competition::factory()->for($this->organizer)->create(['entry_fee' => 5000, 'settings' => ['registration_requires_approval' => false]]);
});

it('signs only the private channels the signed-in accounts may read', function () {
    $artist = User::factory()->create();
    $judge = User::factory()->create();
    $this->competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    $wanted = [Channel::backOffice($this->competition->id), Channel::organizer($this->organizer->id), Channel::jury($this->competition->id), Channel::user($artist->id), Channel::ADMIN];

    expect(tokenChannels(RealtimeToken::issue($wanted, ['web' => $this->owner])))->toBe([Channel::backOffice($this->competition->id), Channel::organizer($this->organizer->id)])
        ->and(tokenChannels(RealtimeToken::issue($wanted, ['jury' => $judge])))->toBe([Channel::jury($this->competition->id)])
        ->and(tokenChannels(RealtimeToken::issue($wanted, ['member' => $artist])))->toBe([Channel::user($artist->id)])
        ->and(RealtimeToken::issue($wanted, ['web' => User::factory()->create()]))->toBeNull();
});

it('tells the organizer about registrations and payments, and the artist about the payment', function () {
    $artist = User::factory()->create();
    $participant = app(RegistrationService::class)->register($artist, $this->competition, 'MC Live');
    app(PaymentService::class)->simulate($participant, PaymentMethod::Wave);

    [$registered] = $this->realtime->sent('participant.registered');
    expect($registered['channels'])->toContain(Channel::backOffice($this->competition->id), Channel::organizer($this->organizer->id))
        ->and($registered['message'])->toBe('Nouvelle inscription : MC Live');

    $paid = collect($this->realtime->sent('payment.paid'));
    expect($paid->firstWhere('channels', [Channel::user($artist->id)])['message'])->toContain('5 000 XOF confirmé')
        ->and($paid->first(fn ($m) => in_array(Channel::backOffice($this->competition->id), $m['channels'], true))['message'])->toBe('Paiement reçu : MC Live · 5 000 XOF');
});

it('announces a submission to review, then its validation to the artist, the public and the jury', function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);
    $entry = preselectionEntry($artists[0]);

    expect(collect($this->realtime->sent('preselection.en_attente'))->pluck('message')->filter()->first())->toBe('Prestation à valider : Artiste 1');

    app(SubmissionService::class)->approve($entry, $owner);
    $approved = collect($this->realtime->sent('preselection.validee'));

    expect($approved->pluck('channels')->flatten()->all())->toContain(Channel::competition($competition->id), Channel::jury($competition->id), Channel::user($artists[0]->user_id))
        ->and($approved->pluck('message')->filter()->values()->all())->toContain('Ta prestation est validée : elle est visible du public et du jury.');
});

it('never sends vote updates to the public page unless live results are shown', function () {
    $phase = Phase::factory()->for($this->competition)->started()->create();
    [$a, $b] = Participant::factory()->for($this->competition)->count(2)->create();
    $match = BattleMatch::factory()->create(['phase_id' => $phase->id]);
    $match->participants()->attach($a->id, ['slot' => 1]);
    $match->participants()->attach($b->id, ['slot' => 2]);
    $match->forceFill(['status' => MatchStatus::Voting])->save();

    expect(collect($this->realtime->sent('match.voting'))->pluck('message')->filter()->first())->toStartWith('Le vote est ouvert');

    app(VotingService::class)->cast(User::factory()->create(), $match, $a->id);
    $vote = collect($this->realtime->sent('vote.cast'))->first(fn ($m) => in_array(Channel::backOffice($this->competition->id), $m['channels'], true));

    expect($vote['channels'])->not->toContain(Channel::competition($this->competition->id));

    $this->competition->update(['settings' => ['show_live_results' => true]]);
    $this->travel(3)->seconds();
    app(VotingService::class)->cast(User::factory()->create(), $match->fresh(), $b->id);

    expect(collect($this->realtime->sent('vote.cast'))->pluck('channels')->flatten())->toContain(Channel::competition($this->competition->id));
});

it('publishes the buffer in one call to the Socket.IO server, and survives it being down', function () {
    config(['realtime.enabled' => true, 'realtime.publish_url' => 'http://realtime.test']);
    Http::fake(['realtime.test/*' => Http::response(['sent' => 2], 202)]);
    $realtime = new Realtime;

    $realtime->push([Channel::competition(1), Channel::backOffice(1)], 'demo', ['x' => 1], 'Salut');
    $realtime->push([Channel::competition(1), Channel::backOffice(1)], 'demo', ['x' => 1], 'Salut');
    $realtime->flush();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'http://realtime.test/publish'
        && $request->hasHeader('Authorization', 'Bearer '.config('realtime.secret'))
        && count($request['messages']) === 1);

    // Server down: logged, never thrown.
    Http::fake(['realtime.test/*' => Http::response(null, 500)]);
    $realtime->push([Channel::LIVE], 'demo');
    expect(fn () => $realtime->flush())->not->toThrow(Throwable::class);
});

it('declares the channels of the page with a signed token', function () {
    config(['realtime.enabled' => true]);

    $html = $this->actingAs($this->owner, 'web')->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))->assertOk()->getContent();
    preg_match('/<script type="application\/json" data-realtime>(.*?)<\/script>/s', $html, $m);
    $config = json_decode($m[1], true);

    expect(tokenChannels($config['token']))->toBe([Channel::backOffice($this->competition->id)])
        ->and($html)->toContain('data-live="tab-preselection"');
});

it('gives the mobile app its connection details', function () {
    config(['realtime.enabled' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/realtime?channels[]='.Channel::competition($this->competition->id).'&channels[]='.Channel::backOffice($this->competition->id))
        ->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('channels', [Channel::competition($this->competition->id)]);

    expect(tokenChannels($response->json('token')))->toBe([Channel::user($user->id)]);
});
