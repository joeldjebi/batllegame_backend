<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Kind of media a participant can submit.
 */
enum MediaType: string
{
    use EnumHelpers;

    case Video = 'video';
    case Audio = 'audio';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Vidéo',
            self::Audio => 'Audio',
        };
    }

    /**
     * Accepted MIME types.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Video => ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska', 'video/3gpp'],
            self::Audio => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm'],
        };
    }

    public static function fromMime(string $mime): ?self
    {
        foreach (self::cases() as $type) {
            if (in_array($mime, $type->mimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }
}
