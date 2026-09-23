<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Tags read by ffprobe (format + streams). Returns nothing when ffprobe is not installed.
 */
class FfprobeTagReader implements MediaTagReader
{
    private static ?bool $available = null;

    public function tags(string $absolutePath): array
    {
        $binary = config('media.ffprobe');
        self::$available ??= is_executable($binary) || (new ExecutableFinder)->find($binary) !== null;

        if (! self::$available) {
            return [];
        }

        $result = Process::timeout(30)->run([
            $binary, '-v', 'error', '-print_format', 'json',
            '-show_entries', 'format_tags:stream_tags', $absolutePath,
        ]);

        if (! $result->successful()) {
            return [];
        }

        $json = json_decode($result->output(), true) ?: [];
        $tags = [];

        foreach ([$json['format']['tags'] ?? [], ...array_column($json['streams'] ?? [], 'tags')] as $group) {
            foreach ((array) $group as $key => $value) {
                $key = strtolower((string) $key);
                if (is_scalar($value) && ! isset($tags[$key]) && mb_strlen((string) $value) <= 500) {
                    $tags[$key] = (string) $value;
                }
            }
        }

        return $tags;
    }
}
