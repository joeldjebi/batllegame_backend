<?php

namespace App\Services\Media;

use App\Enums\MediaType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Streaming optimization with ffmpeg, without losing quality:
 *  - H.264 (8-bit 4:2:0) + AAC/MP3 in MP4/MOV: remuxed with the index first (lossless);
 *  - anything else (HEVC, VP9, WebM, MKV, 3GP…): re-encoded in H.264 CRF 20, which every
 *    phone and browser decodes, long side capped (1920 px by default), never upscaled;
 *  - a JPEG poster taken at 25 % of the video (1 s max).
 */
class FfmpegMediaOptimizer implements MediaOptimizer
{
    private const array COPYABLE_AUDIO = ['aac', 'mp3'];

    public function optimize(string $absolutePath, MediaType $type): ?OptimizedMedia
    {
        if ($type !== MediaType::Video) {
            return new OptimizedMedia;
        }

        $probe = $this->probe($absolutePath);
        $video = collect($probe['streams'] ?? [])->firstWhere('codec_type', 'video');

        if ($video === null) {
            return null;
        }

        $audio = collect($probe['streams'])->firstWhere('codec_type', 'audio');
        $reencode = ! $this->isStreamable($probe, $video, $audio);
        $output = $this->temp('mp4');

        $result = Process::timeout(1800)->run([
            config('media.ffmpeg'), '-v', 'error', '-y', '-i', $absolutePath,
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '0',
            ...($reencode ? $this->encodeArguments($audio) : ['-c', 'copy']),
            '-movflags', '+faststart', $output,
        ]);

        if (! $result->successful() || ! filesize($output)) {
            @unlink($output);
            Log::warning('ffmpeg could not optimize the media; the original file is kept.', ['path' => $absolutePath, 'error' => trim($result->errorOutput()) ?: 'ffmpeg unavailable']);

            return null;
        }

        // Display size of the produced file (a re-encode applies the rotation, a remux keeps it as metadata).
        [$width, $height] = $this->displaySize($reencode ? (collect($this->probe($output)['streams'] ?? [])->firstWhere('codec_type', 'video') ?? $video) : $video);

        return new OptimizedMedia(
            videoPath: $output,
            posterPath: $this->poster($output, (float) ($probe['format']['duration'] ?? 0)),
            width: $width,
            height: $height,
            reencoded: $reencode,
        );
    }

    /**
     * @param  array<string, mixed>  $probe
     * @param  array<string, mixed>  $video
     * @param  ?array<string, mixed>  $audio
     */
    private function isStreamable(array $probe, array $video, ?array $audio): bool
    {
        $container = explode(',', (string) ($probe['format']['format_name'] ?? ''));

        return ($video['codec_name'] ?? null) === 'h264'
            && in_array($video['pix_fmt'] ?? null, ['yuv420p', 'yuvj420p'], true)
            && ($audio === null || in_array($audio['codec_name'] ?? null, self::COPYABLE_AUDIO, true))
            && array_intersect($container, ['mp4', 'mov']) !== [];
    }

    /**
     * @param  ?array<string, mixed>  $audio
     * @return list<string>
     */
    private function encodeArguments(?array $audio): array
    {
        $max = max(320, config('media.max_video_size'));

        return [
            '-vf', "scale='min({$max},iw)':'min({$max},ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
            ...($audio === null ? [] : (in_array($audio['codec_name'] ?? null, self::COPYABLE_AUDIO, true) ? ['-c:a', 'copy'] : ['-c:a', 'aac', '-b:a', '192k'])),
        ];
    }

    private function poster(string $video, float $duration): ?string
    {
        $poster = $this->temp('jpg');
        $width = max(160, config('media.poster_width'));

        $result = Process::timeout(120)->run([
            config('media.ffmpeg'), '-v', 'error', '-y', '-ss', (string) round(min(1.0, $duration * 0.25), 2), '-i', $video,
            '-frames:v', '1', '-vf', "scale='min({$width},iw)':-2", '-q:v', '3', $poster,
        ]);

        if ($result->successful() && filesize($poster)) {
            return $poster;
        }

        @unlink($poster);

        return null;
    }

    /**
     * @param  array<string, mixed>  $stream
     * @return array{0: ?int, 1: ?int}
     */
    private function displaySize(array $stream): array
    {
        $width = isset($stream['width']) ? (int) $stream['width'] : null;
        $height = isset($stream['height']) ? (int) $stream['height'] : null;
        $rotation = (int) ($stream['tags']['rotate'] ?? collect($stream['side_data_list'] ?? [])->firstWhere('rotation')['rotation'] ?? 0);

        return abs($rotation) % 180 === 90 ? [$height, $width] : [$width, $height];
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(string $path): array
    {
        $result = Process::timeout(60)->run([
            config('media.ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);

        return $result->successful() ? (json_decode($result->output(), true) ?: []) : [];
    }

    private function temp(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'opt');
        @unlink($base);

        return "{$base}.{$extension}";
    }
}
