<?php

use App\Enums\OrganizerRole;
use App\Http\Middleware\AdminIdleTimeout;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;

beforeEach(function () {
    $this->country = Country::factory()->ivoryCoast();
    $this->admin = User::factory()->platformAdmin()->create(['phone' => '+2250700000001', 'email' => 'sa@example.com']);
    $this->organizer = User::factory()->create(['phone' => '+2250700000002', 'email' => 'organisateur@example.com']);
});

function emailCredentials(string $email, string $password = 'password'): array
{
    return ['email' => $email, 'password' => $password];
}

function phoneCredentials(string $phone, string $password = 'password'): array
{
    return ['country_id' => Country::query()->value('id'), 'phone' => $phone, 'password' => $password];
}

it('serves the admin login under the configured path', function () {
    expect(route('admin.login', absolute: false))->toBe('/'.config('admin.path').'/login');

    $this->get(route('admin.login'))->assertOk()->assertSee('Administration de la plateforme');
});

it('logs the platform admin in by email on the admin guard only', function () {
    $this->post(route('admin.login'), emailCredentials('sa@example.com'))
        ->assertRedirect(route('admin.organizers.index'));

    $this->assertAuthenticatedAs($this->admin, 'admin');
    $this->assertGuest('web');
});

it('refuses organizers on the admin login with a generic message', function () {
    $this->post(route('admin.login'), emailCredentials('organisateur@example.com'))
        ->assertSessionHasErrors(['email' => 'Identifiants invalides.']);

    $this->assertGuest('admin');
});

it('refuses the platform admin on the organizer login without revealing the account', function () {
    $this->post(route('login'), emailCredentials('sa@example.com'))
        ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);

    $this->assertGuest('web');
});

it('logs organizers in by email on the organizer login', function () {
    $this->post(route('login'), emailCredentials('organisateur@example.com'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->organizer, 'web');
});

it('never gives mobile API tokens to the platform admin', function () {
    $this->postJson('/api/auth/login', phoneCredentials('0700000001'))->assertJsonValidationErrors('phone');
});

it('throttles admin login attempts', function () {
    config(['admin.login_attempts_per_minute' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->post(route('admin.login'), emailCredentials('sa@example.com', 'wrong'))->assertSessionHasErrors('email');
    }

    // Correct password, but locked out.
    $this->post(route('admin.login'), emailCredentials('sa@example.com'))->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Trop de tentatives');

    $this->assertGuest('admin');
});

it('closes the admin session after inactivity', function () {
    $this->actingAs($this->admin, 'admin')
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(config('admin.idle_timeout') + 1)->timestamp])
        ->get(route('admin.organizers.index'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});

it('kicks a platform admin session out of the organizer back-office', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest('web');
});

it('matches back-office emails case-insensitively', function () {
    $this->post(route('login'), emailCredentials('  Organisateur@Example.COM '))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->organizer, 'web');
});

it('refuses a phone number on the back-office login', function () {
    $this->post(route('login'), phoneCredentials('0700000002'))->assertSessionHasErrors('email');

    $this->assertGuest('web');
});

it('keeps phone + password login for mobile users', function () {
    $this->postJson('/api/auth/login', phoneCredentials('0700000002'))->assertOk()->assertJsonStructure(['token']);
});

it('adds an organizer member by email', function () {
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $this->organizer)->create();
    $staff = User::factory()->create(['email' => 'staff@example.com']);

    $this->actingAs($this->organizer)
        ->post(route('organizers.members.store', $organizer), ['email' => 'STAFF@example.com', 'role' => 'staff'])
        ->assertSessionHasNoErrors();

    expect($staff->roleIn($organizer))->toBe(OrganizerRole::Staff);

    $this->actingAs($this->organizer)
        ->post(route('organizers.members.store', $organizer), ['email' => 'unknown@example.com'])
        ->assertSessionHasErrors('email');
});
