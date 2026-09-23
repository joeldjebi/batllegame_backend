<?php

use App\Enums\MediaOrigin;
use App\Enums\PerformanceStatus;
use App\Enums\RecordingPeriod;
use App\Models\PreselectionSubmission;
use App\Services\Media\MediaProvenance;
use App\Services\Media\Mp4MetadataReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

function provenanceOf(string $path): array
{
    return app(MediaProvenance::class)->analyze($path);
}

it('reads an iPhone video: device, software, recording date and GPS presence', function () {
    $path = fakeMp4(['brand' => 'qt  ', 'created' => Carbon::parse('2026-09-20 18:00:00'), 'handler' => 'Core Media Video', 'pascal' => true, 'keys' => [
        'com.apple.quicktime.make' => 'Apple',
        'com.apple.quicktime.model' => 'iPhone 15 Pro',
        'com.apple.quicktime.software' => '17.5.1',
        'com.apple.quicktime.creationdate' => '2026-09-20T18:32:11+0000',
        'com.apple.quicktime.location.ISO6709' => '+05.3600-004.0083/',
    ]]);

    $raw = app(Mp4MetadataReader::class)->read($path);
    expect($raw['brand'])->toBe('qt')->and($raw['handlers'])->toBe(['Core Media Video']);

    ['recorded_at' => $at, 'origin' => $origin, 'metadata' => $meta] = provenanceOf($path);

    expect($origin)->toBe(MediaOrigin::Device)
        ->and($at->toIso8601String())->toBe('2026-09-20T18:32:11+00:00')
        ->and($meta)->toMatchArray(['device' => 'Apple iPhone 15 Pro', 'software' => '17.5.1', 'date_source' => 'appareil', 'has_location' => true])
        ->and($meta)->not->toHaveKey('location');
});

it('uses the movie header date of an Android recording', function () {
    ['recorded_at' => $at, 'origin' => $origin, 'metadata' => $meta] = provenanceOf(fakeMp4([
        'created' => Carbon::parse('2025-12-24 20:15:00'), 'handler' => 'VideoHandle', 'keys' => ['com.android.version' => '14'],
    ]));

    expect($origin)->toBe(MediaOrigin::Device)->and($meta['device'])->toBe('Android')->and($at->format('Y-m-d H:i'))->toBe('2025-12-24 20:15');
});

it('recognizes a TikTok download, a CapCut export, a re-encoded file and stripped metadata', function () {
    expect(provenanceOf(fakeMp4(['udta' => ['too' => 'Lavf58.76.100', 'cmt' => 'vid:v0d004g10000cq1abc']])))
        ->origin->toBe(MediaOrigin::Platform)
        ->metadata->toMatchArray(['platform' => 'TikTok']);

    expect(provenanceOf(fakeMp4(['created' => Carbon::parse('2026-09-21'), 'udta' => ['swr' => 'CapCut 12.4']])))
        ->origin->toBe(MediaOrigin::EditingApp)
        ->metadata->toMatchArray(['app' => 'Capcut']);

    expect(provenanceOf(fakeMp4(['handler' => 'VideoHandler', 'udta' => ['too' => 'Lavf60.3.100']])))
        ->origin->toBe(MediaOrigin::Reencoded)
        ->recorded_at->toBeNull();

    expect(provenanceOf(fakeMp4()))->origin->toBe(MediaOrigin::Stripped);

    $audio = tempnam(sys_get_temp_dir(), 'mp3');
    file_put_contents($audio, 'ID3'.str_repeat("\0", 64));
    expect(provenanceOf($audio))->origin->toBe(MediaOrigin::Unknown);
});

it('stores the provenance of an entry and compares it with the pre-selection period', function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(2, settings: ['submissions_require_approval' => true]);
    $competition->preselection->update(['starts_at' => now()->subDays(3)]);

    $upload = fn (Carbon $recorded) => new UploadedFile(fakeMp4(['created' => $recorded, 'handler' => 'Core Media Video', 'pascal' => true, 'keys' => [
        'com.apple.quicktime.make' => 'Apple', 'com.apple.quicktime.model' => 'iPhone 13', 'com.apple.quicktime.creationdate' => $recorded->toIso8601String(),
    ]]), 'take.mov', 'video/quicktime', null, true);

    $this->actingAs($artists[0]->user, 'member')
        ->post(route('artist.competitions.preselection.submit', $competition), ['media' => $upload(now()->subDay()), 'client_modified_at' => now()->subDay()->getTimestampMs()])
        ->assertSessionHasNoErrors();
    $this->actingAs($artists[1]->user, 'member')
        ->post(route('artist.competitions.preselection.submit', $competition), ['media' => $upload(now()->subMonths(4))]);

    [$fresh, $old] = PreselectionSubmission::query()->orderBy('id')->get()->all();

    expect($fresh)
        ->status->toBe(PerformanceStatus::Pending)
        ->media_origin->toBe(MediaOrigin::Device)
        ->and($fresh->recordingPeriod())->toBe(RecordingPeriod::During)
        ->and($fresh->client_modified_at)->not->toBeNull()
        ->and($old->recordingPeriod())->toBe(RecordingPeriod::Before);

    $this->actingAs($owner, 'web')->get(route('organizers.competitions.show', [$organizer, $competition]))
        ->assertOk()
        ->assertSee('Enregistré pendant la période')
        ->assertSee('avant la période')
        ->assertSee('Apple iPhone 13');
});

it('flags a recording date after the upload as inconsistent', function () {
    $entry = new PreselectionSubmission;
    $entry->forceFill(['recorded_at' => now()->addDays(2), 'media_metadata' => ['uploaded_at' => now()->toIso8601String()]]);

    expect($entry->recordingPeriod())->toBe(RecordingPeriod::Inconsistent);
});

it('analyzes media uploaded before the feature with media:provenance', function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
    ['artists' => $artists] = competitionWithPreselection(1);
    $entry = preselectionEntry($artists[0]);
    Storage::disk('public')->put($entry->media_path, file_get_contents(fakeMp4(['handler' => 'ISO Media file produced by Google Inc.', 'udta' => ['too' => 'Lavf59.27.100']])));
    $entry->forceFill(['media_origin' => null])->saveQuietly();

    $this->artisan('media:provenance')->expectsOutputToContain('1 média(s) analysé(s)')->assertSuccessful();

    expect($entry->fresh())->media_origin->toBe(MediaOrigin::Platform)->media_metadata->toMatchArray(['platform' => 'YouTube']);
});
