<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\PerformanceStatus;
use App\Models\Performance;
use App\Models\PreselectionSubmission;
use App\Models\User;
use App\Services\Competition\StageService;
use App\Services\SubmissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

/**
 * An approved stage submission of the first artist of a running online competition.
 */
function approvedStagePerformance(): Performance
{
    ['phase' => $phase, 'participants' => $participants] = startedCompetition(CompetitionMode::Online, 2);
    $stage = $phase->stages()->orderBy('number')->first();
    app(StageService::class)->schedule($stage, ['submission_deadline' => now()->addDay()]);
    app(StageService::class)->openSubmissions($stage);

    return app(SubmissionService::class)->approve(app(SubmissionService::class)->submit($participants[0], $stage->fresh(), fakeVideo())->fresh(), User::factory()->create());
}

it('lists approved entries and stage performances of public competitions, newest first', function () {
    ['artists' => $artists, 'competition' => $competition] = competitionWithPreselection(3);
    [$old, $new, $pending] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $old->forceFill(['published_at' => now()->subHour()])->save();
    $pending->forceFill(['status' => PerformanceStatus::Pending])->save();
    $performance = approvedStagePerformance();
    $performance->forceFill(['published_at' => now()->subMinutes(30)])->save();

    // Not public: a draft competition.
    ['artists' => $hidden, 'competition' => $draft] = competitionWithPreselection(1);
    preselectionEntry($hidden[0]);
    $draft->update(['status' => CompetitionStatus::Draft]);

    $response = $this->getJson('/api/feed')->assertOk();

    expect($response->json('data.*.key'))->toBe(["preselection-{$new->id}", "performance-{$performance->id}", "preselection-{$old->id}"])
        ->and($response->json('meta.next_cursor'))->toBeNull();

    $response->assertJsonPath('data.0.artist.stage_name', $new->participant->stage_name)
        ->assertJsonPath('data.0.competition.slug', $competition->slug)
        ->assertJsonPath('data.0.context.label', 'Présélection')
        ->assertJsonPath('data.0.likes.liked', false)
        ->assertJsonPath('data.0.share_url', route('fan.competitions.preselection.entry', [$competition, $new]))
        ->assertJsonPath('data.1.vote.match_id', $performance->stage->matches()->first()->id)
        ->assertJsonPath('data.1.likes', null);
    expect($response->json('data.0.media'))->toHaveKeys(['url', 'poster_url', 'width', 'height', 'duration_seconds']);
});

it('pages with a stable cursor, without duplicates or gaps at the same second', function () {
    Carbon::setTestNow('2026-09-25 12:00:00');
    ['artists' => $artists] = competitionWithPreselection(7);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));
    $entries->each(fn (PreselectionSubmission $entry, int $i) => $entry->forceFill(['published_at' => $i < 4 ? now() : now()->subDay()])->save());

    $keys = [];
    $cursor = null;
    do {
        $page = $this->getJson('/api/feed?limit=3'.($cursor ? "&cursor={$cursor}" : ''))->assertOk();
        $keys = [...$keys, ...$page->json('data.*.id')];
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor);

    expect($keys)->toBe([...$entries->take(4)->pluck('id')->reverse()->values()->all(), ...$entries->slice(4)->pluck('id')->reverse()->values()->all()]);

    // A like moves updated_at, never the order.
    likeAs($entries[0]);
    expect($this->getJson('/api/feed?limit=30')->json('data.*.id'))->toBe($keys);

    $this->getJson('/api/feed?cursor=garbage')->assertOk()->assertJsonCount(7, 'data');
    $this->getJson('/api/feed?limit=500')->assertStatus(422);
});

it('tells a signed-in viewer which entry they liked and shows the counts after their like', function () {
    ['artists' => $artists] = competitionWithPreselection(2);
    [$first, $second] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $fan = likeAs($second);

    $this->getJson('/api/feed')->assertJsonPath('data.0.likes.count', null);

    $this->actingAs($fan, 'sanctum')->getJson('/api/feed')->assertOk()
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.likes.liked', true)
        ->assertJsonPath('data.0.likes.count', 1)
        ->assertJsonPath('data.1.likes.liked', false)
        ->assertJsonPath('data.1.likes.count', 0);

    // An artist always sees the count of their own entry.
    $this->actingAs($first->participant->user, 'sanctum')->getJson('/api/feed')
        ->assertJsonPath('data.1.likes.count', 0)
        ->assertJsonPath('data.0.likes.count', null);
});

it('filters by competition and discipline', function () {
    ['artists' => $a, 'competition' => $rap] = competitionWithPreselection(1);
    ['artists' => $b, 'competition' => $slam] = competitionWithPreselection(1);
    $rap->update(['discipline' => 'rap']);
    $slam->update(['discipline' => 'slam']);
    preselectionEntry($a[0]);
    $slamEntry = preselectionEntry($b[0]);

    $this->getJson("/api/feed?competition={$slam->slug}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $slamEntry->id);
    $this->getJson('/api/feed?discipline=slam')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $slamEntry->id);
    $this->getJson('/api/feed?competition=inconnue')->assertNotFound();
    $this->getJson('/api/feed?discipline=opera')->assertStatus(422);
});

it('publishes a media when it is approved and withdraws it when a new take is sent', function () {
    ['artists' => $artists] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);
    $entry = preselectionEntry($artists[0]);
    expect($entry->published_at)->toBeNull();

    app(SubmissionService::class)->approve($entry, User::factory()->create());
    expect($entry->fresh()->published_at)->not->toBeNull();
    $this->getJson('/api/feed')->assertJsonCount(1, 'data');

    preselectionEntry($artists[0]);
    expect($entry->fresh())->status->toBe(PerformanceStatus::Pending)->published_at->toBeNull();
    $this->getJson('/api/feed')->assertJsonCount(0, 'data');
});
