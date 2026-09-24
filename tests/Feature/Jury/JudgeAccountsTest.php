<?php

use App\Enums\CompetitionMode;
use App\Enums\JudgeStatus;
use App\Enums\MatchStatus;
use App\Enums\OrganizerRole;
use App\Models\Competition;
use App\Models\Country;
use App\Models\User;
use App\Services\JudgeAccountService;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    $this->country = Country::factory()->ivoryCoast();
    $this->sms = Mockery::spy(SmsSender::class);
    $this->app->instance(SmsSender::class, $this->sms);

    ['competition' => $this->competition, 'organizer' => $this->organizer, 'owner' => $this->owner] = startedCompetition(CompetitionMode::OnSite);
    $this->url = route('organizers.competitions.judges.store', [$this->organizer, $this->competition]);
});

it('creates a judge account with a temporary password sent by SMS', function () {
    $this->actingAs($this->owner)
        ->post($this->url, ['name' => 'Juge Didi', 'country_id' => $this->country->id, 'phone' => '0501020304'])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('phone', '+2250501020304')->firstOrFail();

    expect($user->name)->toBe('Juge Didi')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->isJudgeOf($this->competition))->toBeTrue()
        ->and($this->competition->judges()->first()->status)->toBe(JudgeStatus::Accepted);

    $this->sms->shouldHaveReceived('send')->with('+2250501020304', Mockery::pattern('/mot de passe provisoire \w{10}/'))->once();
});

it('reuses an existing account without resetting its password', function () {
    $existing = User::factory()->create(['phone' => '+2250501020304']);

    $this->actingAs($this->owner)
        ->post($this->url, ['name' => 'Autre nom', 'country_id' => $this->country->id, 'phone' => '0501020304'])
        ->assertSessionHasNoErrors();

    expect(User::query()->where('phone', '+2250501020304')->count())->toBe(1)
        ->and($existing->fresh()->must_change_password)->toBeFalse()
        ->and($existing->isJudgeOf($this->competition))->toBeTrue();

    $this->sms->shouldHaveReceived('send')->with('+2250501020304', Mockery::pattern('/espace juré/'))->once();
});

it('refuses a participant of the same competition as judge', function () {
    $participant = $this->competition->participants()->first()->user;
    $national = substr($participant->phone, 4);

    $this->actingAs($this->owner)
        ->post($this->url, ['name' => 'X', 'country_id' => $this->country->id, 'phone' => $national])
        ->assertSessionHasErrors('phone');
});

it('lets only competition managers create judges', function () {
    $staff = User::factory()->create();
    $this->organizer->users()->attach($staff, ['role' => OrganizerRole::Staff]);

    $this->actingAs($staff)
        ->post($this->url, ['name' => 'X', 'country_id' => $this->country->id, 'phone' => '0501020304'])
        ->assertForbidden();
});

it('forces a created judge to change the temporary password before using the judge area', function () {
    $this->actingAs($this->owner)->post($this->url, ['name' => 'Juge', 'country_id' => $this->country->id, 'phone' => '0501020304']);
    $judge = User::query()->where('phone', '+2250501020304')->firstOrFail();
    $judge->forceFill(['password' => 'temporary-pass'])->save();

    $this->actingAs($judge, 'sanctum')->getJson('/api/judge/competitions')
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');

    $this->actingAs($judge, 'sanctum')->postJson('/api/auth/password', [
        'current_password' => 'temporary-pass',
        'password' => 'my-new-password',
        'password_confirmation' => 'my-new-password',
    ])->assertOk()->assertJsonPath('data.must_change_password', false);

    $this->actingAs($judge->fresh(), 'sanctum')->getJson('/api/judge/competitions')->assertOk()->assertJsonCount(1, 'data');
});

it('shows a judge only the competitions they are assigned to', function () {
    $judge = User::factory()->create();
    $this->competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    $other = Competition::factory()->inProgress()->create();

    $this->actingAs($judge, 'sanctum')->getJson('/api/judge/competitions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $this->competition->slug);

    $this->actingAs($judge, 'sanctum')->getJson("/api/judge/competitions/{$other->slug}")->assertNotFound();
    $this->actingAs($judge, 'sanctum')->getJson("/api/judge/competitions/{$this->competition->slug}")->assertOk();
});

it('lists the matches a judge has to score with the criteria', function () {
    $judge = User::factory()->create();
    $this->competition->judges()->create(['user_id' => $judge->id, 'status' => JudgeStatus::Accepted]);
    $this->competition->criteria()->create(['name' => 'Flow', 'max_points' => 10, 'weight' => 1]);
    $match = $this->competition->matches()->where('round', 1)->first();
    $match->forceFill(['status' => MatchStatus::Voting])->save();

    $this->actingAs($judge, 'sanctum')->getJson("/api/judge/competitions/{$this->competition->slug}")
        ->assertOk()
        ->assertJsonPath('criteria.0.name', 'Flow')
        ->assertJsonPath('matches.0.id', $match->id);
});

it('gives new judges the default temporary password while no SMS service exists', function () {
    config(['accounts.judge_default_password' => '12345678']);
    $competition = Competition::factory()->create();

    [$judge, $password] = app(JudgeAccountService::class)->assign($competition, $this->country, '0501010101', 'Juge Défaut');

    expect($password)->toBe('12345678')
        ->and(Hash::check('12345678', $judge->user->password))->toBeTrue()
        ->and($judge->user->must_change_password)->toBeTrue();

    config(['accounts.judge_default_password' => null]);
    [, $random] = app(JudgeAccountService::class)->assign($competition, $this->country, '0501010102', 'Juge Aléatoire');
    expect($random)->not->toBe('12345678')->toHaveLength(10);
});
