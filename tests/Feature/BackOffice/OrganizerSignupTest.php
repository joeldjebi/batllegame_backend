<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use App\Realtime\Channel;
use App\Realtime\Realtime;

beforeEach(function () {
    $this->realtime = app(Realtime::class)->fake();
    $this->country = Country::query()->where('is_active', true)->first() ?? Country::factory()->create(['is_active' => true]);
});

function signupData(array $overrides = []): array
{
    return [
        'organizer_name' => 'Yop City Battle', 'city' => 'Abidjan',
        'name' => 'Awa Koné', 'email' => 'Awa@Example.com', 'country_id' => test()->country->id, 'phone' => '0701020304',
        'password' => 'battle2026', 'password_confirmation' => 'battle2026', 'terms' => '1',
        ...$overrides,
    ];
}

it('lets a visitor open a pending organizer space and signs them in', function () {
    $this->get(route('organizers.signup'))->assertOk()->assertSee('Créez votre espace organisateur');

    $response = $this->post(route('organizers.signup'), signupData());

    $organizer = Organizer::query()->where('name', 'Yop City Battle')->firstOrFail();
    $owner = User::query()->where('email', 'awa@example.com')->firstOrFail();

    $response->assertRedirect(route('organizers.show', $organizer))->assertSessionHas('status');
    $this->assertAuthenticatedAs($owner, 'web');
    expect($organizer->status)->toBe(OrganizerStatus::Pending)
        ->and($organizer->members()->where('user_id', $owner->id)->value('role'))->toBe(OrganizerRole::Owner)
        ->and($owner->phone)->toBe($this->country->toE164('0701020304'))
        ->and($owner->must_change_password)->toBeFalsy()
        ->and($this->realtime->sent('organizer.registered')[0])->toMatchArray(['channels' => [Channel::ADMIN], 'message' => 'Nouvel organisateur à vérifier : Yop City Battle']);

    $this->get(route('organizers.show', $organizer))->assertOk()->assertSee('Vérification en cours');
});

it('validates the sign-up', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('organizers.signup'), signupData(['terms' => null, 'password' => 'short', 'password_confirmation' => 'short']))
        ->assertSessionHasErrors(['terms', 'password']);
    $this->post(route('organizers.signup'), signupData(['email' => 'taken@example.com']))->assertSessionHasErrors('email');

    expect(Organizer::query()->count())->toBe(0);
});

it('links an existing mobile account only with its password', function () {
    $artist = User::factory()->create(['email' => null, 'country_id' => $this->country->id, 'phone' => $this->country->toE164('0701020304'), 'password' => 'artist2026']);

    $this->post(route('organizers.signup'), signupData())->assertSessionHasErrors('phone');
    expect(Organizer::query()->count())->toBe(0)->and($artist->fresh()->email)->toBeNull();

    $this->post(route('organizers.signup'), signupData(['password' => 'artist2026', 'password_confirmation' => 'artist2026']))->assertRedirect();

    expect($artist->fresh()->email)->toBe('awa@example.com')
        ->and(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs($artist->fresh(), 'web');
});

it('lets a signed-in organizer open one more organizer', function () {
    $owner = User::factory()->create();
    Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();

    $this->actingAs($owner, 'web')->get(route('organizers.signup'))->assertOk()->assertSee('Vous serez propriétaire');
    $this->actingAs($owner, 'web')->post(route('organizers.signup'), ['organizer_name' => 'Deuxième scène', 'terms' => '1'])->assertRedirect();

    expect($owner->organizers()->count())->toBe(2)
        ->and(Organizer::query()->where('name', 'Deuxième scène')->value('status'))->toBe(OrganizerStatus::Pending);
});

it('keeps registrations closed until the super-admin verifies the organizer', function () {
    $this->post(route('organizers.signup'), signupData());
    $organizer = Organizer::query()->firstOrFail();
    $owner = $organizer->users()->first();
    $competition = Competition::factory()->for($organizer)->create(['status' => CompetitionStatus::Draft]);

    expect($owner->can('changeStatus', [$competition, CompetitionStatus::Registration]))->toBeFalse();

    $admin = User::factory()->platformAdmin()->create(['email' => 'sa@example.com']);
    $this->actingAs($admin, 'admin')->patch(route('admin.organizers.status', $organizer), ['status' => OrganizerStatus::Verified->value])->assertRedirect();

    expect($organizer->fresh()->isVerified())->toBeTrue()
        ->and($owner->can('changeStatus', [$competition->fresh(), CompetitionStatus::Registration]))->toBeTrue()
        ->and(collect($this->realtime->sent('organizer.status'))->firstWhere(fn ($m) => in_array(Channel::organizer($organizer->id), $m['channels'], true))['message'])->toContain('vous pouvez ouvrir les inscriptions');
});

it('can be turned off', function () {
    config(['organizers.self_signup' => false]);

    $this->get(route('organizers.signup'))->assertNotFound();
    $this->post(route('organizers.signup'), signupData())->assertNotFound();
    $this->get(route('login'))->assertDontSee('Créer mon espace organisateur');
});
