<?php

use App\Enums\MediaType;
use App\Enums\PerformanceStatus;
use App\Http\Resources\MediaResource;
use App\Jobs\OptimizeMedia;
use App\Models\PreselectionSubmission;
use App\Services\Media\FfmpegMediaOptimizer;
use App\Services\Media\MediaOptimizer;
use App\Services\Media\OptimizedMedia;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

/**
 * Optimizer double: produces a new video + poster, or fails the way asked.
 */
function fakeOptimizer(string $mode = 'ok', ?Closure $during = null): void
{
    app()->instance(MediaOptimizer::class, new class($mode, $during) implements MediaOptimizer
    {
        public function __construct(private string $mode, private ?Closure $during) {}

        public function optimize(string $absolutePath, MediaType $type): ?OptimizedMedia
        {
            ($this->during)?->__invoke();

            return match ($this->mode) {
                'null' => null,
                'throw' => throw new RuntimeException('ffmpeg crashed'),
                default => new OptimizedMedia(
                    videoPath: tap(tempnam(sys_get_temp_dir(), 'vid'), fn ($p) => file_put_contents($p, 'optimized-video')),
                    posterPath: tap(tempnam(sys_get_temp_dir(), 'pos'), fn ($p) => file_put_contents($p, 'jpeg')),
                    width: 720,
                    height: 1280,
                ),
            };
        }
    });
}

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public', 'media.optimize' => true]);
    fakeMediaDuration(60);
});

it('replaces the upload by the optimized video and stores its poster before the review', function () {
    fakeOptimizer();
    ['artists' => $artists] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);

    $entry = preselectionEntry($artists[0]);

    expect($entry)
        ->status->toBe(PerformanceStatus::Pending)
        ->media_path->toEndWith('.mp4')
        ->mime_type->toBe('video/mp4')
        ->size_bytes->toBe(strlen('optimized-video'))
        ->poster_path->toEndWith('-poster.jpg')
        ->width->toBe(720)
        ->height->toBe(1280)
        ->optimized_at->not->toBeNull();

    $disk = Storage::disk('public');
    expect($disk->get($entry->media_path))->toBe('optimized-video')
        ->and($disk->exists($entry->poster_path))->toBeTrue()
        ->and($disk->allFiles("preselections/{$entry->competition_id}"))->toHaveCount(2)
        ->and((new MediaResource($entry))->resolve())->toMatchArray(['width' => 720, 'height' => 1280])
        ->and((new MediaResource($entry))->resolve()['poster_url'])->toContain($entry->poster_path);
});

it('keeps the original file when the optimization fails, without blocking the entry', function (string $mode) {
    fakeOptimizer($mode);
    ['artists' => $artists] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);

    $entry = preselectionEntry($artists[0]);

    expect($entry)
        ->status->toBe(PerformanceStatus::Pending)
        ->poster_path->toBeNull()
        ->optimized_at->toBeNull()
        ->and(Storage::disk('public')->exists($entry->media_path))->toBeTrue();
})->with(['tool missing' => 'null', 'crash' => 'throw']);

it('drops the result when the artist replaced the file meanwhile', function () {
    ['artists' => $artists] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);
    config(['media.optimize' => false]);
    $entry = preselectionEntry($artists[0]);
    config(['media.optimize' => true]);
    fakeOptimizer(during: fn () => PreselectionSubmission::query()->whereKey($entry->id)->update(['media_path' => 'preselections/other.mp4']));

    OptimizeMedia::dispatchSync($entry);

    expect($entry->fresh())
        ->media_path->toBe('preselections/other.mp4')
        ->poster_path->toBeNull()
        ->optimized_at->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('removes the poster with the file when the artist sends a new take', function () {
    fakeOptimizer();
    ['artists' => $artists] = competitionWithPreselection(1, settings: ['submissions_require_approval' => true]);
    $first = preselectionEntry($artists[0]);

    $second = preselectionEntry($artists[0]);

    expect($second->id)->toBe($first->id)
        ->and($second->poster_path)->not->toBe($first->poster_path)
        ->and(Storage::disk('public')->exists($first->poster_path))->toBeFalse()
        ->and(Storage::disk('public')->exists($first->media_path))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
});

it('optimizes media uploaded before the feature with media:optimize', function () {
    ['artists' => $artists] = competitionWithPreselection(2, settings: ['submissions_require_approval' => true]);
    config(['media.optimize' => false]);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));
    config(['media.optimize' => true]);
    fakeOptimizer();

    $this->artisan('media:optimize', ['--sync' => true])->expectsOutputToContain('2 média(s) traité(s)')->assertSuccessful();
    expect($entries->map(fn ($entry) => $entry->fresh()->optimized_at)->filter())->toHaveCount(2);

    Bus::fake();
    $this->artisan('media:optimize')->expectsOutputToContain('0 média(s)')->assertSuccessful();
    Bus::assertNothingDispatched();
});

it('does nothing when the optimization is disabled', function () {
    config(['media.optimize' => false]);

    $this->artisan('media:optimize')->expectsOutputToContain('désactivée')->assertSuccessful();
});

it('keeps the original when ffmpeg is not installed', function () {
    Process::fake(['*' => Process::result(errorOutput: 'not found', exitCode: 127)]);

    expect(app(FfmpegMediaOptimizer::class)->optimize('/tmp/none.mp4', MediaType::Video))->toBeNull()
        ->and(app(FfmpegMediaOptimizer::class)->optimize('/tmp/none.m4a', MediaType::Audio))->toEqual(new OptimizedMedia);
});

/*
 * Real ffmpeg (skipped when it is not installed): lossless remux with the index first,
 * re-encode of HEVC into H.264, rotation, poster.
 */
describe('with ffmpeg', function () {
    beforeEach(function () {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg || ls /opt/homebrew/bin/ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            $this->markTestSkipped('ffmpeg is not installed.');
        }
        config(['media.ffmpeg' => $ffmpeg, 'media.ffprobe' => dirname($ffmpeg).'/ffprobe']);
        $this->make = function (string $args, string $extension): string {
            $path = tempnam(sys_get_temp_dir(), 'src').'.'.$extension;
            exec(config('media.ffmpeg')." -v error -y {$args} ".escapeshellarg($path), result_code: $code);
            expect($code)->toBe(0);

            return $path;
        };
        $this->probe = fn (string $path) => json_decode((string) shell_exec(config('media.ffprobe').' -v error -print_format json -show_streams '.escapeshellarg($path)), true)['streams'][0];
        $this->moovFirst = fn (string $path) => ($data = file_get_contents($path)) && strpos($data, 'moov') < strpos($data, 'mdat');
    });

    it('remuxes H.264 without re-encoding and moves the index first', function () {
        $source = ($this->make)('-f lavfi -i testsrc2=duration=2:size=640x360:rate=25 -f lavfi -i sine=duration=2 -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest', 'mp4');
        expect(($this->moovFirst)($source))->toBeFalse();

        $result = app(FfmpegMediaOptimizer::class)->optimize($source, MediaType::Video);

        expect($result)->reencoded->toBeFalse()->width->toBe(640)->height->toBe(360)
            ->and(($this->moovFirst)($result->videoPath))->toBeTrue()
            ->and(($this->probe)($result->videoPath)['codec_name'])->toBe('h264')
            ->and(getimagesize($result->posterPath)[0])->toBe(640);
        $result->cleanup();
    });

    it('re-encodes HEVC into H.264 and reports the display size of a portrait video', function () {
        $hevc = ($this->make)('-f lavfi -i testsrc2=duration=1:size=640x360:rate=25 -c:v libx265 -tag:v hvc1 -pix_fmt yuv420p', 'mov');
        $result = app(FfmpegMediaOptimizer::class)->optimize($hevc, MediaType::Video);

        expect($result->reencoded)->toBeTrue()
            ->and(($this->probe)($result->videoPath))->toMatchArray(['codec_name' => 'h264', 'pix_fmt' => 'yuv420p', 'width' => 640, 'height' => 360]);
        $result->cleanup();

        $landscape = ($this->make)('-f lavfi -i testsrc2=duration=1:size=640x360:rate=25 -c:v libx264 -pix_fmt yuv420p', 'mp4');
        $portrait = tempnam(sys_get_temp_dir(), 'rot').'.mp4';
        exec(config('media.ffmpeg').' -v error -y -display_rotation 90 -i '.escapeshellarg($landscape).' -c copy '.escapeshellarg($portrait));
        $rotated = app(FfmpegMediaOptimizer::class)->optimize($portrait, MediaType::Video);

        expect($rotated)->width->toBe(360)->height->toBe(640)
            ->and(array_slice(getimagesize($rotated->posterPath), 0, 2))->toBe([360, 640]);
        $rotated->cleanup();
    });
});
