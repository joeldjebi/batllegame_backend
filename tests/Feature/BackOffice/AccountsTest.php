<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Competition\StageService;
use App\Services\Sms\SmsSender;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    $this->country = Country::factory()->ivoryCoast();
    $this->sms = Mockery::spy(SmsSender::class);
    $this->app->instance(SmsSender::class, $this->sms);
    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->owner)->create();
});

it('lets an owner create a manager account with a temporary password', function () {
    $response = $this->actingAs($this->owner, 'web')->post(route('organizers.members.store', $this->organizer), [
        'name' => 'Awa Manager', 'email' => 'Awa@Example.com', 'country_id' => $this->country->id, 'phone' => '0102030405', 'role' => 'admin',
    ])->assertSessionHasNoErrors();

    $manager = User::query()->where('email', 'awa@example.com')->firstOrFail();

    expect($manager->must_change_password)->toBeTrue()
        ->and($manager->phone)->toBe('+2250102030405')
        ->and($manager->roleIn($this->organizer))->toBe(OrganizerRole::Admin)
        ->and(session('status'))->toContain('Mot de passe provisoire');

    $this->sms->shouldHaveReceived('send')->with('+2250102030405', Mockery::pattern('/awa@example.com.*provisoire/'))->once();

    // First login: the manager must replace the temporary password.
    preg_match('/provisoire : (\w+)/', session('status'), $m);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'awa@example.com', 'password' => $m[1]])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('password.edit'));
    $this->put(route('password.update'), ['current_password' => $m[1], 'password' => 'manager-pass-1', 'password_confirmation' => 'manager-pass-1'])
        ->assertRedirect(route('dashboard'));
    $this->get(route('organizers.show', $this->organizer))->assertOk();
});

it('adds an existing back-office account by email without touching it', function () {
    $existing = User::factory()->create(['email' => 'staff@example.com']);

    $this->actingAs($this->owner, 'web')
        ->post(route('organizers.members.store', $this->organizer), ['email' => 'staff@example.com', 'role' => 'staff'])
        ->assertSessionHasNoErrors();

    expect($existing->fresh()->must_change_password)->toBeFalse()
        ->and($existing->roleIn($this->organizer))->toBe(OrganizerRole::Staff);
    $this->sms->shouldNotHaveReceived('send');
});

it('turns a mobile account into a back-office account when the phone matches', function () {
    $mobile = User::factory()->create(['phone' => '+2250102030405', 'email' => null]);

    $this->actingAs($this->owner, 'web')->post(route('organizers.members.store', $this->organizer), [
        'name' => 'X', 'email' => 'mobile@example.com', 'country_id' => $this->country->id, 'phone' => '0102030405', 'role' => 'staff',
    ])->assertSessionHasNoErrors();

    expect($mobile->fresh()->email)->toBe('mobile@example.com')->and($mobile->isMemberOf($this->organizer))->toBeTrue();
});

it('refuses a phone already used by another back-office account', function () {
    User::factory()->create(['phone' => '+2250102030405', 'email' => 'someone@example.com']);

    $this->actingAs($this->owner, 'web')->post(route('organizers.members.store', $this->organizer), [
        'name' => 'X', 'email' => 'new@example.com', 'country_id' => $this->country->id, 'phone' => '0102030405', 'role' => 'staff',
    ])->assertSessionHasErrors('phone');
});

it('does not let organizers create organizers', function () {
    $this->actingAs($this->owner, 'web')->post('/organizers', ['name' => 'Nouvelle structure'])->assertNotFound();
    $this->actingAs($this->owner, 'web')->get(route('dashboard'))->assertOk()->assertDontSee('Nouvel organisateur');

    expect(Organizer::query()->count())->toBe(1);
});

it('lets the super-admin create an organizer with its owner', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.organizers.store'), [
        'name' => 'Yopougon Battle Club', 'city' => 'Abidjan', 'status' => 'verifie',
        'owner_name' => 'Koffi Owner', 'owner_email' => 'koffi@example.com', 'country_id' => $this->country->id, 'phone' => '0506070809',
    ])->assertSessionHasNoErrors();

    $organizer = Organizer::query()->where('name', 'Yopougon Battle Club')->firstOrFail();
    $owner = User::query()->where('email', 'koffi@example.com')->firstOrFail();

    expect($organizer->status)->toBe(OrganizerStatus::Verified)
        ->and($organizer->verified_at)->not->toBeNull()
        ->and($owner->roleIn($organizer))->toBe(OrganizerRole::Owner)
        ->and($owner->must_change_password)->toBeTrue();

    $this->actingAs($admin, 'admin')->get(route('admin.organizers.index'))->assertOk()->assertSee('Nouvel organisateur');
});

it('shows the temporary password of a created judge to the organizer', function () {
    $competition = Competition::factory()->for($this->organizer)->create();

    $this->actingAs($this->owner, 'web')->post(route('organizers.competitions.judges.store', [$this->organizer, $competition]), [
        'name' => 'Juge Web', 'country_id' => $this->country->id, 'phone' => '0501010101',
    ])->assertSessionHasNoErrors();

    expect(session('status'))->toMatch('/Mot de passe provisoire : \w{10}/');
});

it('reopens the right modal on validation errors', function () {
    $competition = Competition::factory()->for($this->organizer)->create();

    $this->actingAs($this->owner, 'web')
        ->from(route('organizers.competitions.show', [$this->organizer, $competition]))
        ->post(route('organizers.competitions.judges.store', [$this->organizer, $competition]), ['_form' => 'invite-judge', 'country_id' => $this->country->id, 'phone' => '0501010101'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->owner, 'web')
        ->get(route('organizers.competitions.show', [$this->organizer, $competition]))
        ->assertOk()
        ->assertSee('x-data="{ open: true }"', false)
        // Blade directives are never left unrendered in the page (they break Alpine).
        ->assertDontSee('@js(', false);
});

it('renders the landing page with live votes and open competitions', function () {
    ['competition' => $running, 'phase' => $phase] = startedCompetition(CompetitionMode::OnSite, 2);
    $running->update(['name' => 'Battle en direct']);
    app(StageService::class)->openMatchVoting($phase->matches()->first());
    Competition::factory()->create(['status' => CompetitionStatus::Registration, 'name' => 'Open Mic Plateau']);
    Competition::factory()->draft()->create(['name' => 'Brouillon secret']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Battle en direct')
        ->assertSee('Open Mic Plateau')
        ->assertDontSee('Brouillon secret')
        ->assertSee(route('fan.register'))
        ->assertSee(route('artist.register'))
        ->assertSee(route('jury.login'));

    expect($phase->matches()->first()->status)->toBe(MatchStatus::Voting);
});

it('sends a new artist to the artist space after the phone verification', function () {
    $code = null;
    $sms = Mockery::mock(SmsSender::class);
    $sms->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$code) {
        preg_match('/\d{6}/', $message, $m);
        $code = $m[0] ?? $code;
    });
    $this->app->instance(SmsSender::class, $sms);

    $this->post('/artiste/inscription', [
        'name' => 'MC Nouveau', 'country_id' => $this->country->id, 'phone' => '0712345678',
        'password' => 'artist-pass', 'password_confirmation' => 'artist-pass',
    ])->assertRedirect(route('fan.verification.show'));

    $this->post(route('fan.verification.verify'), ['code' => $code])->assertRedirect(route('artist.dashboard'));
});

it('does not advertise an expired vote as live', function () {
    ['competition' => $running, 'phase' => $phase] = startedCompetition(CompetitionMode::OnSite, 2);
    $running->update(['name' => 'Vote expiré']);
    app(StageService::class)->openMatchVoting($phase->matches()->first(), now()->subMinute());

    $this->get('/')->assertOk()->assertDontSee('fin il y a');
    $this->get(route('fan.competitions.show', $running))->assertOk()->assertSee('Aucun vote en cours');
});
