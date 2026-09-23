<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\JudgeStatus;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Criterion;
use App\Models\Performance;
use App\Models\User;
use App\Services\Competition\StageService;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    $this->country = Country::factory()->ivoryCoast();
    $this->app->instance(SmsSender::class, Mockery::spy(SmsSender::class));
});

function phoneLogin(User $user, string $password = 'password'): array
{
    return ['country_id' => Country::query()->value('id'), 'phone' => substr($user->phone, 4), 'password' => $password];
}

it('serves a distinct login page for each portal', function () {
    $this->get('/jury/login')->assertOk()->assertSee('Espace jury');
    $this->get('/artiste/login')->assertOk()->assertSee('Espace artiste');
    $this->get('/vote/login')->assertOk()->assertSee('Espace public');
    $this->get('/jury/inscription')->assertNotFound();
});

it('only lets assigned judges into the jury portal', function () {
    ['competition' => $competition] = startedCompetition(CompetitionMode::OnSite);
    $judge = User::factory()->create();
    $competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    $fan = User::factory()->create();

    $this->post('/jury/login', phoneLogin($fan))->assertSessionHasErrors('phone');
    $this->assertGuest('jury');

    $this->post('/jury/login', phoneLogin($judge))->assertRedirect(route('jury.dashboard'));
    $this->assertAuthenticatedAs($judge, 'jury');
    $this->assertGuest('member');
});

it('refuses the platform admin on every portal', function () {
    $admin = User::factory()->platformAdmin()->create();

    foreach (['/artiste/login', '/vote/login'] as $url) {
        $this->post($url, phoneLogin($admin))->assertSessionHasErrors('phone');
    }

    $this->assertGuest('member');
});

it('makes a created judge change the temporary password, then score from the web', function () {
    ['competition' => $competition, 'phase' => $phase] = startedCompetition(CompetitionMode::OnSite, 2, rules: ['vote_mode' => 'jury']);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    $judgeUser = User::factory()->create(['must_change_password' => true]);
    $competition->judges()->create(['user_id' => $judgeUser->id, 'status' => JudgeStatus::Accepted]);
    $match = $phase->matches()->first();
    app(StageService::class)->openMatchVoting($match);

    $this->actingAs($judgeUser, 'jury')->get(route('jury.dashboard'))->assertRedirect(route('jury.password.edit'));

    $this->actingAs($judgeUser, 'jury')->put(route('jury.password.update'), [
        'current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ])->assertRedirect(route('jury.dashboard'));

    $judgeUser->refresh();
    $this->actingAs($judgeUser, 'jury')->get(route('jury.dashboard'))->assertOk()->assertSee($competition->name);
    $this->actingAs($judgeUser, 'jury')->get(route('jury.competitions.matches.show', [$competition, $match]))->assertOk()->assertSee($criterion->name);

    $participantId = $match->slots()->value('participant_id');
    $this->actingAs($judgeUser, 'jury')
        ->post(route('jury.competitions.matches.scores.store', [$competition, $match]), [
            'participant_id' => $participantId,
            'scores' => [['criterion_id' => $criterion->id, 'score' => 7.5]],
        ])->assertSessionHasNoErrors();

    expect($match->juryScores()->sole()->score)->toBe(7.5);

    // Another competition does not exist for this judge.
    $other = Competition::factory()->inProgress()->create();
    $this->actingAs($judgeUser, 'jury')->get(route('jury.competitions.show', $other))->assertNotFound();
});

it('registers an artist, then lets them submit their performance from the web', function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(45);

    $open = Competition::factory()->create(['status' => CompetitionStatus::Registration, 'name' => 'Open Mic Cocody']);
    $artist = User::factory()->create();

    $this->post('/artiste/login', phoneLogin($artist))->assertRedirect(route('artist.dashboard'));
    $this->get(route('artist.dashboard'))->assertOk()->assertSee('Open Mic Cocody');
    $this->post(route('artist.competitions.register', $open), ['stage_name' => 'MC Web'])->assertSessionHasNoErrors();
    expect($artist->isParticipantOf($open))->toBeTrue();

    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online, 2);
    $stage = $phase->stages()->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addDay()]);
    app(StageService::class)->openSubmissions($stage);
    $player = $participants[0]->user;

    $this->actingAs($player, 'member')->get(route('artist.dashboard'))->assertOk()->assertSee('Envoyer ma prestation');
    $this->actingAs($player, 'member')
        ->post(route('artist.competitions.stages.submit', [$phase->competition, $stage]), ['media' => fakeVideo()])
        ->assertSessionHasNoErrors();

    expect(Performance::query()->sole()->participant_id)->toBe($participants[0]->id);
});

it('signs up a fan, verifies the phone by SMS code and votes', function () {
    ['competition' => $competition, 'phase' => $phase] = startedCompetition(CompetitionMode::OnSite, 2, settings: ['onsite_vote_code' => true]);
    $match = $phase->matches()->first();
    app(StageService::class)->openMatchVoting($match);
    $code = $match->fresh()->vote_code;

    $sentCode = null;
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$sentCode) {
        preg_match('/\d{6}/', $message, $m);
        $sentCode = $m[0] ?? $sentCode;
    });
    $this->app->instance(SmsSender::class, $sms);

    $this->get(route('fan.competitions.show', $competition))->assertOk()->assertSee('Connectez-vous pour voter');

    $this->post('/vote/inscription', [
        'name' => 'Fan Web', 'country_id' => $this->country->id, 'phone' => '0709080706',
        'password' => 'fan-password', 'password_confirmation' => 'fan-password',
    ])->assertRedirect(route('fan.verification.show'));

    $fan = User::query()->where('phone', '+2250709080706')->firstOrFail();
    $this->assertAuthenticatedAs($fan, 'member');

    $voteUrl = route('fan.competitions.matches.votes.store', [$competition, $match]);
    $participantId = $match->slots()->value('participant_id');

    // Not verified yet: the policy refuses the vote.
    $this->post($voteUrl, ['participant_id' => $participantId, 'vote_code' => $code])->assertForbidden();

    $this->post(route('fan.verification.verify'), ['code' => $sentCode])->assertRedirect(route('fan.dashboard'));
    $this->post($voteUrl, ['participant_id' => $participantId, 'vote_code' => 'nope'])->assertSessionHasErrors('vote_code');
    $this->post($voteUrl, ['participant_id' => $participantId, 'vote_code' => $code])->assertSessionHasNoErrors();
    $this->post($voteUrl, ['participant_id' => $participantId, 'vote_code' => $code])->assertSessionHasErrors('vote');

    expect($match->publicVotes()->count())->toBe(1);
    $this->get(route('fan.competitions.show', $competition))->assertOk()->assertSee('Votre vote');
});

it('keeps portal sessions separate from the organizer back-office', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'member')->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs($user, 'member')->get(route('jury.dashboard'))->assertRedirect(route('jury.login'));
});
