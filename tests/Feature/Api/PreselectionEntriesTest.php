<?php

use App\Enums\CompetitionStatus;
use App\Services\PreselectionService;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('pages the public entries with a cursor, newest first, and searches by stage name', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(5);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));

    $first = $this->getJson("/api/competitions/{$competition->slug}/preselection/entries?limit=2")->assertOk();
    expect($first->json('data.*.id'))->toBe([$entries[4]->id, $entries[3]->id])
        ->and($first->json('data.0'))->toHaveKeys(['stage_name', 'avatar_url', 'media.poster_url', 'likes', 'liked', 'share_url']);

    $ids = $first->json('data.*.id');
    $cursor = $first->json('meta.next_cursor');
    while ($cursor) {
        $page = $this->getJson("/api/competitions/{$competition->slug}/preselection/entries?limit=2&cursor={$cursor}")->assertOk();
        $ids = [...$ids, ...$page->json('data.*.id')];
        $cursor = $page->json('meta.next_cursor');
    }
    expect($ids)->toBe($entries->pluck('id')->reverse()->values()->all());

    $this->getJson("/api/competitions/{$competition->slug}/preselection/entries?q=Artiste 3")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $entries[2]->id);
});

it('orders the entries by rank once the selection is published', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(3);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));
    likeAs($entries[1]);
    likeAs($entries[1]);
    likeAs($entries[2]);
    $competition->preselection->forceFill(['ends_at' => now()->subDays(3), 'vote_ends_at' => now()->subDays(2), 'deliberation_hours' => 0])->save();
    app(PreselectionService::class)->rank($competition->preselection->fresh());
    app(PreselectionService::class)->publish($competition->preselection->fresh());

    $response = $this->getJson("/api/competitions/{$competition->slug}/preselection/entries")->assertOk();

    expect($response->json('data.*.id'))->toBe([$entries[1]->id, $entries[2]->id, $entries[0]->id])
        ->and($response->json('data.*.rank'))->toBe([1, 2, 3])
        ->and($response->json('data.0.selected'))->toBeTrue()
        ->and($response->json('data.0.likes'))->toBe(2);
});

it('hides the entries of a draft competition', function () {
    ['competition' => $competition] = competitionWithPreselection(1);
    $competition->update(['status' => CompetitionStatus::Draft]);

    $this->getJson("/api/competitions/{$competition->slug}/preselection/entries")->assertNotFound();
});
