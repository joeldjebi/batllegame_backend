<?php

namespace App\Services\Media;

use App\Enums\MediaOrigin;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Guesses when and how a media was produced from its hidden metadata: recording date,
 * device, editing app, social platform traces, re-encoding or stripped metadata.
 * Only an indication for the organizer (metadata can be removed or edited).
 * The GPS position is never stored, only whether one is present.
 */
class MediaProvenance
{
    private const string EDITING_APPS = '/\b(capcut|inshot|kinemaster|vllo|vn video|videoleap|lumafusion|imovie|final cut|premiere|davinci|filmora|splice|powerdirector|youcut|picsart|canva)\b/i';

    private const array PLATFORMS = [
        'TikTok' => '/vid:v[0-9a-z]|tiktok|bytedance|aigc/i',
        'YouTube' => '/google|youtube/i',
        'Instagram' => '/instagram/i',
        'Facebook' => '/facebook|fbcdn/i',
        'Snapchat' => '/snapchat/i',
    ];

    /** Tools that rewrite the file and drop the device information. */
    private const string REENCODERS = '/^(lavf|lavc)|videohandler|soundhandler|bento4|l-smash|mainconcept|handbrake|x264|mp4box|gpac/i';

    public function __construct(private Mp4MetadataReader $mp4, private MediaTagReader $ffprobe) {}

    /**
     * @return array{recorded_at: ?Carbon, origin: MediaOrigin, metadata: array<string, mixed>}
     */
    public function analyze(string $path): array
    {
        $mp4 = $this->isIsoMedia($path) ? $this->mp4->read($path) : ['brand' => null, 'created_at' => null, 'handlers' => [], 'tags' => []];
        $tags = [...$this->ffprobe->tags($path), ...$mp4['tags']];
        $pick = fn (string ...$keys) => collect($keys)->map(fn ($k) => $tags[$k] ?? null)->first(fn ($v) => filled($v));

        $make = $pick('com.apple.quicktime.make', 'mak', 'make', 'com.android.manufacturer');
        $model = $pick('com.apple.quicktime.model', 'mod', 'model', 'com.android.model');
        $software = $pick('com.apple.quicktime.software', 'swr', 'software');
        $encoder = $pick('too', 'encoder', 'encoded_by');
        $android = $pick('com.android.version');
        $handlers = array_slice($mp4['handlers'], 0, 4);
        $text = implode(' ', [...array_values($tags), ...$handlers]);

        [$recordedAt, $dateSource] = $this->recordedAt($pick, $mp4['created_at']);

        $app = preg_match(self::EDITING_APPS, implode(' ', array_filter([$software, $encoder, $pick('cmt', 'comment', 'description')])), $m) ? ucwords(strtolower($m[1])) : null;
        $platform = collect(self::PLATFORMS)->keys()->first(fn (string $name) => preg_match(self::PLATFORMS[$name], $text) === 1);
        $appleCamera = collect($handlers)->contains(fn ($h) => str_starts_with($h, 'Core Media')) && $make === null && $software !== null && preg_match('/^\d+(\.\d+)*$/', $software);
        $androidCamera = $android !== null || collect($handlers)->contains(fn ($h) => in_array($h, ['VideoHandle', 'SoundHandle'], true));
        $device = trim(implode(' ', array_unique(array_filter([$make, $model])))) ?: ($appleCamera ? 'iPhone / iPad' : ($androidCamera ? 'Android' : null));
        $reencoded = collect([$encoder, ...$handlers])->filter()->contains(fn ($v) => preg_match(self::REENCODERS, $v) === 1);

        $origin = match (true) {
            $app !== null => MediaOrigin::EditingApp,
            $platform !== null => MediaOrigin::Platform,
            $device !== null => MediaOrigin::Device,
            $reencoded => MediaOrigin::Reencoded,
            $recordedAt === null && $software === null && $encoder === null => $mp4['brand'] !== null || $tags !== [] || $handlers !== [] ? MediaOrigin::Stripped : MediaOrigin::Unknown,
            default => MediaOrigin::Unknown,
        };

        return [
            'recorded_at' => $recordedAt,
            'origin' => $origin,
            'metadata' => array_filter([
                'date_source' => $dateSource,
                'device' => $device,
                'software' => $software,
                'encoder' => $encoder,
                'app' => $app,
                'platform' => $platform,
                'android_version' => $android,
                'brand' => $mp4['brand'],
                'handlers' => $handlers ?: null,
                'has_location' => filled($pick('com.apple.quicktime.location.iso6709', 'xyz', 'location', 'location-eng')) ?: null,
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * @return array{0: ?Carbon, 1: ?string}
     */
    private function recordedAt(callable $pick, ?Carbon $movieHeader): array
    {
        $candidates = [
            'appareil' => $pick('com.apple.quicktime.creationdate'),
            'balise date' => $pick('day', 'date'),
            'conteneur' => $pick('creation_time'),
        ];

        foreach ($candidates as $source => $value) {
            // A bare year ("2026") is not a recording date.
            if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && ($date = rescue(fn () => Carbon::parse($value), null, false))) {
                if ($date->year >= 2000) {
                    return [$date->utc(), $source];
                }
            }
        }

        return $movieHeader ? [$movieHeader, 'conteneur'] : [null, null];
    }

    private function isIsoMedia(string $path): bool
    {
        try {
            $handle = fopen($path, 'rb');
            $head = (string) fread($handle, 12);
            fclose($handle);

            return in_array(substr($head, 4, 4), ['ftyp', 'moov', 'wide', 'mdat', 'free'], true);
        } catch (Throwable) {
            return false;
        }
    }
}
