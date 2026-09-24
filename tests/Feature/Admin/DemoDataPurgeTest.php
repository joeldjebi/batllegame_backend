<?php

use App\Enums\OrganizerRole;
use App\Enums\SeedKind;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\User;
use App\Services\DemoDataPurger;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoCompetitionSeeder;
use Database\Seeders\LocalAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $env = [
        'SEED_ADMIN_EMAIL' => 'sa@example.com', 'SEED_ADMIN_PHONE' => '0700000001', 'SEED_ADMIN_PASSWORD' => 'secret-admin',
        'SEED_ORGANIZER_EMAIL' => 'orga@example.com', 'SEED_ORGANIZER_PHONE' => '0700000002', 'SEED_ORGANIZER_PASSWORD' => 'secret-orga',
        'SEED_ARTIST_PHONE' => '0700000004', 'SEED_ARTIST_PASSWORD' => 'secret-artist',
    ];
    foreach ($env as $key => $value) {
        $_SERVER[$key] = $_ENV[$key] = $value;
    }

    $this->seed([CountrySeeder::class, RoleSeeder::class, LocalAccountsSeeder::class, DemoCompetitionSeeder::class]);
    $this->admin = User::query()->where('email', 'sa@example.com')->firstOrFail();

    // Real data next to the demo: a real organizer and competition, a real fan active on a demo competition,
    // and a demo artist who also registered to the real competition.
    $this->real = Competition::factory()->for(Organizer::factory()->withMember(OrganizerRole::Owner)->create())->create(['name' => 'Vraie compétition']);
    $this->fan = User::factory()->create();
    Participant::factory()->for(Competition::query()->where('seed_kind', SeedKind::Demo)->first())->create(['user_id' => $this->fan->id]);
    $this->demoArtist = User::query()->where('seed_kind', SeedKind::Demo)->where('name', 'Tchoko')->firstOrFail();
    Participant::factory()->for($this->real)->create(['user_id' => $this->demoArtist->id]);
});

afterEach(function () {
    foreach (array_keys($_SERVER) as $key) {
        if (str_starts_with($key, 'SEED_')) {
            unset($_SERVER[$key], $_ENV[$key]);
        }
    }
});

it('tags what the seeders create', function () {
    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->count())->toBe(4)
        ->and(User::query()->where('seed_kind', SeedKind::Demo)->count())->toBeGreaterThan(18)
        ->and(User::query()->where('seed_kind', SeedKind::TestAccount)->pluck('email')->filter()->all())->toContain('orga@example.com')
        ->and(Organizer::query()->where('slug', 'organisateur-test')->value('seed_kind'))->toBe(SeedKind::TestAccount)
        ->and($this->admin->seed_kind)->toBeNull();
});

it('removes the demo data and keeps real data, admins and test accounts', function () {
    $testAccounts = User::query()->where('seed_kind', SeedKind::TestAccount)->count();
    $result = app(DemoDataPurger::class)->purge();

    expect($result['competitions'])->toBe(4)
        ->and($result['kept_users'])->toBe(1)
        ->and(Competition::withTrashed()->where('seed_kind', SeedKind::Demo)->exists())->toBeFalse()
        ->and(User::query()->where('seed_kind', SeedKind::Demo)->pluck('id')->all())->toBe([$this->demoArtist->id])
        ->and($this->real->fresh())->not->toBeNull()
        ->and($this->real->participants()->count())->toBe(1)
        ->and($this->fan->fresh())->not->toBeNull()
        ->and($this->fan->participations()->count())->toBe(0)
        ->and($this->admin->fresh())->not->toBeNull()
        ->and(User::query()->where('seed_kind', SeedKind::TestAccount)->count())->toBe($testAccounts)
        ->and(Organizer::query()->where('slug', 'organisateur-test')->exists())->toBeTrue();
});

it('also removes the test organizer and accounts on request', function () {
    app(DemoDataPurger::class)->purge(withAccounts: true);

    expect(Organizer::query()->where('slug', 'organisateur-test')->exists())->toBeFalse()
        ->and(User::query()->where('seed_kind', SeedKind::TestAccount)->exists())->toBeFalse()
        ->and($this->admin->fresh())->not->toBeNull()
        ->and($this->real->fresh())->not->toBeNull();
});

it('lets the super-admin empty the demo data from the console', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertSee('Données de test')->assertSee('Vider les données de test');

    $this->actingAs($this->admin, 'admin')->delete(route('admin.demo-data.destroy'), ['confirmation' => 'non'])
        ->assertSessionHasErrors('confirmation');
    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->count())->toBe(4);

    $this->actingAs($this->admin, 'admin')->delete(route('admin.demo-data.destroy'), ['confirmation' => 'VIDER', 'with_accounts' => '0'])
        ->assertRedirect(route('admin.dashboard'))->assertSessionHas('status');

    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->exists())->toBeFalse();
    $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('Organisateur de test');
});

it('is reserved to the super-admin', function () {
    $owner = User::query()->where('email', 'orga@example.com')->first();

    $this->actingAs($owner, 'web')->delete(route('admin.demo-data.destroy'), ['confirmation' => 'VIDER'])->assertRedirect();
    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->count())->toBe(4);
});

it('only previews from the command line without --force', function () {
    $this->artisan('demo:purge', ['--accounts' => true])->assertSuccessful();
    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->count())->toBe(4);

    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();
    expect(Competition::query()->where('seed_kind', SeedKind::Demo)->exists())->toBeFalse();
});
