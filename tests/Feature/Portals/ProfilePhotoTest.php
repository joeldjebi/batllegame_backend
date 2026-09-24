<?php

use App\Enums\OrganizerRole;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('lets an artist add, change and remove a profile photo', function () {
    $artist = User::factory()->create();

    $this->actingAs($artist, 'member')->get(route('artist.profile.edit'))->assertOk()->assertSee('Photo de profil');
    $this->actingAs($artist, 'member')->put(route('artist.profile.update'), ['name' => 'Awa Koné', 'photo' => UploadedFile::fake()->image('me.jpg', 1200, 800)])
        ->assertSessionHasNoErrors();

    $path = $artist->fresh()->avatar_path;
    Storage::disk('public')->assertExists($path);
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect([$width, $height])->toBe([512, 512])->and($artist->fresh()->name)->toBe('Awa Koné');

    $this->actingAs($artist, 'member')->put(route('artist.profile.update'), ['name' => 'Awa Koné', 'photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');

    $this->actingAs($artist, 'member')->put(route('artist.profile.update'), ['name' => 'Awa Koné', 'remove_photo' => '1'])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($path);
    expect($artist->fresh()->avatar_path)->toBeNull();
});

it('updates the profile and the photo from the mobile app', function () {
    $artist = User::factory()->create();

    $this->actingAs($artist, 'sanctum')->post('/api/auth/profile', ['name' => 'Lil Zoro', 'photo' => UploadedFile::fake()->image('me.png', 600, 600)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.name', 'Lil Zoro')->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, 'avatars/'));
});

it('shows the organizer the photo and the personal details of each participant', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create();
    $artist = User::factory()->create(['name' => 'Awa Koné', 'email' => 'awa@example.com', 'avatar_path' => 'avatars/awa.webp']);
    Participant::factory()->for($competition)->create(['user_id' => $artist->id, 'stage_name' => 'Awa Voice']);

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertOk()
        ->assertSee('Voir la fiche')->assertSee('awa@example.com')->assertSee($artist->phone)->assertSee('avatars/awa.webp')
        ->assertSee('https://wa.me/'.ltrim($artist->phone, '+'));

    // Another organizer never sees them.
    $other = User::factory()->create();
    Organizer::factory()->withMember(OrganizerRole::Owner, $other)->create();
    $this->actingAs($other, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertNotFound();
});
