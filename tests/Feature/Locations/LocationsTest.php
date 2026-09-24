<?php

use App\Enums\OrganizerRole;
use App\Models\City;
use App\Models\Commune;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use App\Support\Locations;

beforeEach(function () {
    $this->admin = User::factory()->platformAdmin()->create(['email' => 'sa@example.com']);
    $this->country = Country::factory()->ivoryCoast();
});

function abidjan(): City
{
    $city = test()->country->cities()->create(['name' => 'Abidjan']);
    $city->communes()->createMany([['name' => 'Cocody'], ['name' => 'Yopougon']]);

    return $city;
}

it('lets the super-admin create countries, cities and communes by hand', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.locations.index'))->assertOk()->assertSee('Pays, villes, communes');

    $this->actingAs($this->admin, 'admin')->post(route('admin.locations.countries.store'), [
        'name' => 'Sénégal', 'iso2' => 'sn', 'iso3' => 'sen', 'dial_code' => '+221', 'phone_min_length' => 9, 'phone_max_length' => 9, 'flag' => '🇸🇳', 'is_active' => '1',
    ])->assertRedirect();
    $senegal = Country::query()->where('iso2', 'SN')->firstOrFail();

    // One per line: blanks, duplicates and existing names ignored.
    $this->actingAs($this->admin, 'admin')->post(route('admin.locations.cities.store', $senegal), ['cities' => "Dakar\n  Thiès \n\ndakar\nSaint-Louis"])->assertRedirect();
    $this->actingAs($this->admin, 'admin')->post(route('admin.locations.cities.store', $senegal), ['cities' => 'Dakar'])->assertSessionHasErrors('cities');
    expect($senegal->cities()->orderBy('position')->pluck('name')->all())->toBe(['Dakar', 'Thiès', 'Saint-Louis']);

    $dakar = $senegal->cities()->where('name', 'Dakar')->first();
    $this->actingAs($this->admin, 'admin')->post(route('admin.locations.communes.store', $dakar), ['communes' => "Plateau\nMédina"])->assertRedirect();
    $this->actingAs($this->admin, 'admin')->put(route('admin.locations.cities.update', $dakar), ['name' => 'Dakar Ville', 'position' => 0, 'is_active' => '1'])->assertRedirect();

    expect($dakar->fresh()->name)->toBe('Dakar Ville')
        ->and($dakar->communes()->orderBy('position')->pluck('name')->all())->toBe(['Plateau', 'Médina']);

    $this->actingAs($this->admin, 'admin')->get(route('admin.locations.index', ['country' => $senegal->id, 'city' => $dakar->id]))
        ->assertOk()->assertSee('Communes · Dakar Ville')->assertSee('Médina');
});

it('offers only active places, refreshed as soon as the super-admin changes them', function () {
    $city = abidjan();
    expect(collect(Locations::tree()[0]['cities'])->firstWhere('name', 'Abidjan')['communes'])->toHaveCount(2);

    $this->actingAs($this->admin, 'admin')->put(route('admin.locations.communes.update', $city->communes()->where('name', 'Yopougon')->first()), ['is_active' => '0'])->assertRedirect();
    expect(collect(Locations::tree()[0]['cities'])->firstWhere('name', 'Abidjan')['communes'])->toBe([['id' => $city->communes()->where('name', 'Cocody')->value('id'), 'name' => 'Cocody']]);

    $this->getJson('/api/locations')->assertOk()->assertJsonPath('data.0.cities.0.name', 'Abidjan');
});

it('never deletes a place in use, nor the last active country', function () {
    $city = abidjan();
    $unused = $this->country->cities()->create(['name' => 'Bouaké']);
    Organizer::factory()->create(['city_id' => $city->id, 'commune_id' => $city->communes()->first()->id]);

    $this->actingAs($this->admin, 'admin')->delete(route('admin.locations.cities.destroy', $city))->assertSessionHasErrors('location');
    $this->actingAs($this->admin, 'admin')->delete(route('admin.locations.communes.destroy', $city->communes()->first()))->assertSessionHasErrors('location');
    $this->actingAs($this->admin, 'admin')->delete(route('admin.locations.cities.destroy', $unused))->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->admin, 'admin')->delete(route('admin.locations.countries.destroy', $this->country))->assertSessionHasErrors('location');
    $this->actingAs($this->admin, 'admin')->put(route('admin.locations.countries.update', $this->country), ['is_active' => '0'])->assertSessionHasErrors('is_active');

    expect(City::query()->whereKey($city->id)->exists())->toBeTrue()
        ->and(City::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and($this->country->fresh()->is_active)->toBeTrue();
});

it('is reserved to the super-admin', function () {
    $owner = User::factory()->create();
    Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();

    $this->actingAs($owner, 'web')->get(route('admin.locations.index'))->assertRedirect();
    $this->actingAs($owner, 'web')->post(route('admin.locations.countries.store'), ['name' => 'X'])->assertRedirect();
    expect(Country::query()->count())->toBe(1);
});

it('makes organizers pick their city and commune from the lists', function () {
    $city = abidjan();
    $cocody = $city->communes()->where('name', 'Cocody')->first();
    $data = [
        'organizer_name' => 'Yop City Battle', 'name' => 'Awa Koné', 'email' => 'awa@example.com', 'country_id' => $this->country->id,
        'phone' => '0701020304', 'password' => 'battle2026', 'password_confirmation' => 'battle2026', 'terms' => '1',
    ];

    $this->post(route('organizers.signup'), $data)->assertSessionHasErrors('city_id');
    $this->post(route('organizers.signup'), [...$data, 'city_id' => $city->id])->assertSessionHasErrors('commune_id');
    $other = $this->country->cities()->create(['name' => 'Bouaké'])->communes()->create(['name' => 'Air France']);
    $this->post(route('organizers.signup'), [...$data, 'city_id' => $city->id, 'commune_id' => $other->id])->assertSessionHasErrors('commune_id');

    $this->post(route('organizers.signup'), [...$data, 'city_id' => $city->id, 'commune_id' => $cocody->id])->assertRedirect();

    $organizer = Organizer::query()->where('name', 'Yop City Battle')->firstOrFail();
    expect($organizer->locationLabel())->toBe('Cocody, Abidjan');

    // Settings: a city without communes clears the previous commune.
    $bouake = City::query()->where('name', 'Bouaké')->first();
    $bouake->communes()->delete();
    $owner = $organizer->users()->first();
    $this->actingAs($owner, 'web')->put(route('organizers.update', $organizer), ['name' => $organizer->name, 'city_id' => $bouake->id])->assertSessionHasNoErrors();
    expect($organizer->fresh()->locationLabel())->toBe('Bouaké');
});

it('places a competition and keeps its venue when another settings form is saved', function () {
    $city = abidjan();
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create();
    $yopougon = $city->communes()->where('name', 'Yopougon')->first();

    $this->actingAs($owner, 'web')->put(route('organizers.competitions.update', [$organizer, $competition]), ['name' => $competition->name, 'city_id' => $city->id, 'commune_id' => $yopougon->id])->assertSessionHasNoErrors();
    $this->actingAs($owner, 'web')->put(route('organizers.competitions.update', [$organizer, $competition]), ['description' => '<div>Hello</div>'])->assertSessionHasNoErrors();

    expect($competition->fresh()->locationLabel())->toBe('Yopougon, Abidjan');
    $this->getJson("/api/competitions/{$competition->slug}")->assertJsonPath('data.location.label', 'Yopougon, Abidjan');
});

it('lets users give their city when they sign up (optional)', function () {
    $city = abidjan();

    $this->postJson('/api/auth/register', [
        'name' => 'Fan', 'country_id' => $this->country->id, 'phone' => '0501020304', 'password' => 'password1', 'password_confirmation' => 'password1',
        'city_id' => $city->id, 'commune_id' => $city->communes()->first()->id,
    ])->assertCreated()->assertJsonPath('user.location.city.name', 'Abidjan');

    $this->postJson('/api/auth/register', [
        'name' => 'Fan 2', 'country_id' => $this->country->id, 'phone' => '0501020305', 'password' => 'password1', 'password_confirmation' => 'password1',
    ])->assertCreated()->assertJsonPath('user.location', null);

    $this->get(route('fan.register'))->assertOk()->assertSee('Ville (facultatif)');
});
