<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
});

it('lets the organizer set a 16:9 cover, shown in the app, then remove it', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create(['status' => CompetitionStatus::Registration]);
    $url = route('organizers.competitions.cover', [$organizer, $competition]);

    $this->actingAs($owner, 'web')->post($url, ['cover' => UploadedFile::fake()->image('cover.jpg', 1000, 1000)])->assertSessionHasNoErrors();

    $competition->refresh();
    expect($competition->cover_path)->toStartWith('covers/')
        ->and(getimagesizefromstring(Storage::disk('public')->get($competition->cover_path))[0])->toBe(1280)
        ->and(getimagesizefromstring(Storage::disk('public')->get($competition->cover_path))[1])->toBe(720);
    $this->getJson('/api/competitions')->assertJsonPath('data.0.cover_url', fn ($u) => str_contains($u, $competition->cover_path))
        ->assertJsonPath('data.0.participants_count', 0);
    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))->assertSee('Image de couverture');

    $path = $competition->cover_path;
    $this->actingAs($owner, 'web')->post($url, ['remove' => 1])->assertSessionHasNoErrors();
    expect($competition->fresh()->cover_path)->toBeNull()->and(Storage::disk('public')->exists($path))->toBeFalse();

    $this->actingAs(User::factory()->create(), 'web')->post($url, ['cover' => UploadedFile::fake()->image('x.jpg')])->assertNotFound(); // Not a member: the organizer is not even revealed.
});

it('falls back to the poster of a public performance, and gives the first prize', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $competition->update(['prizes' => [['rank' => '1er prix', 'reward' => '500 000 XOF']]]);
    $entry = preselectionEntry($artists[0]);
    $entry->forceFill(['poster_path' => 'preselections/x-poster.jpg', 'media_disk' => 'public'])->save();

    $this->getJson('/api/competitions')->assertOk()
        ->assertJsonPath('data.0.cover_url', fn ($u) => str_contains($u, 'x-poster.jpg'))
        ->assertJsonPath('data.0.top_prize.reward', '500 000 XOF')
        ->assertJsonPath('data.0.participants_count', 1);
});
