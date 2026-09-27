<?php

use App\Enums\CompetitionStatus;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('shows one page per artist account, with all their competitions and videos', function () {
    ['artists' => $artists, 'competition' => $first] = competitionWithPreselection(1);
    $artist = $artists[0];
    ['competition' => $second] = competitionWithPreselection(0);
    $again = Participant::factory()->for($second)->create(['user_id' => $artist->user_id, 'stage_name' => 'Awa Voice']);
    ['competition' => $draft] = competitionWithPreselection(0);
    Participant::factory()->for($draft)->create(['user_id' => $artist->user_id]);
    $draft->update(['status' => CompetitionStatus::Draft]);
    $entry = preselectionEntry($artist);
    preselectionEntry(competitionWithPreselection(1)['artists'][0]); // Someone else.

    $this->getJson("/api/artists/{$again->id}")->assertOk()
        ->assertJsonPath('stage_name', 'Awa Voice')
        ->assertJsonPath('followers_count', 0)
        ->assertJsonPath('following', false)
        ->assertJsonPath('performances_count', 1)
        ->assertJsonCount(2, 'participations')
        ->assertJsonPath('participations.1.competition.slug', $first->slug);

    // Their videos, whichever participation the page was opened from.
    $this->getJson("/api/feed?artist={$again->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $entry->id);
});

it('lets a user follow and unfollow an artist, and lists the artists followed', function () {
    ['artists' => $artists] = competitionWithPreselection(1);
    $artist = $artists[0];
    $fan = User::factory()->create();

    $this->postJson("/api/artists/{$artist->id}/follow")->assertUnauthorized();

    $this->actingAs($fan, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertNoContent();
    $this->actingAs($fan, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertNoContent(); // Twice: once.
    $this->actingAs($fan, 'sanctum')->getJson("/api/artists/{$artist->id}")
        ->assertJsonPath('followers_count', 1)->assertJsonPath('following', true);
    $this->actingAs($fan, 'sanctum')->getJson('/api/me/following')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.participant_id', $artist->id);

    $this->actingAs($artist->user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertStatus(422);

    $this->actingAs($fan, 'sanctum')->deleteJson("/api/artists/{$artist->id}/follow")->assertNoContent();
    $this->actingAs($fan, 'sanctum')->getJson('/api/me/following')->assertJsonCount(0, 'data');
});

it('never shows an artist through a draft competition', function () {
    ['artists' => $artists, 'competition' => $competition] = competitionWithPreselection(1);
    $competition->update(['status' => CompetitionStatus::Draft]);

    $this->getJson("/api/artists/{$artists[0]->id}")->assertNotFound();
});
