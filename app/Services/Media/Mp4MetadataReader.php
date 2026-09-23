<?php

namespace App\Services\Media;

use Illuminate\Support\Carbon;

/**
 * Reads the hidden metadata of an MP4 / MOV / M4A file (ISO base media + QuickTime)
 * without loading the media data: brand, creation time of the movie header, handler
 * names, QuickTime user data (©too, ©swr, ©day, ©mak, ©mod, ©cmt, ©xyz), iTunes
 * tags (meta/ilst) and Apple / Android keys (meta/keys: com.apple.quicktime.*).
 *
 * Pure PHP: works without ffmpeg. Unknown or corrupt files return an empty result.
 */
class Mp4MetadataReader
{
    private const array CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'udta', 'edts', 'dinf', 'stbl'];

    private const int MAX_ATOMS = 4000;

    /** Seconds between 1904-01-01 (QuickTime epoch) and 1970-01-01. */
    private const int EPOCH_OFFSET = 2082844800;

    /** @var resource */
    private $handle;

    private int $atoms = 0;

    /**
     * @var array{brand: ?string, created_at: ?Carbon, handlers: list<string>, tags: array<string, string>}
     */
    private array $result;

    /**
     * @return array{brand: ?string, created_at: ?Carbon, handlers: list<string>, tags: array<string, string>}
     */
    public function read(string $path): array
    {
        $this->result = ['brand' => null, 'created_at' => null, 'handlers' => [], 'tags' => []];
        $this->atoms = 0;

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $this->result;
        }

        $this->handle = $handle;

        try {
            $this->walk(0, (int) fstat($handle)['size'], 0);
        } finally {
            fclose($handle);
        }

        return $this->result;
    }

    private function walk(int $start, int $end, int $depth): void
    {
        $offset = $start;

        while ($offset + 8 <= $end && $depth < 12 && ++$this->atoms < self::MAX_ATOMS) {
            fseek($this->handle, $offset);
            $header = fread($this->handle, 8);
            if (strlen($header) < 8) {
                return;
            }

            ['size' => $size, 'type' => $type] = unpack('Nsize/a4type', $header);
            $headerSize = 8;

            if ($size === 1) {
                $size = $this->uint64(fread($this->handle, 8));
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }

            if ($size < $headerSize || $offset + $size > $end) {
                return;
            }

            $bodyStart = $offset + $headerSize;
            $bodyEnd = $offset + $size;

            match (true) {
                $type === 'ftyp' => $this->result['brand'] = trim($this->bytes($bodyStart, 4)),
                $type === 'mvhd' => $this->movieHeader($bodyStart),
                $type === 'hdlr' => $this->handler($bodyStart, $bodyEnd),
                $type === 'meta' => $this->meta($bodyStart, $bodyEnd, $depth),
                in_array($type, self::CONTAINERS, true) => $this->walk($bodyStart, $bodyEnd, $depth + 1),
                $depth > 0 && str_starts_with($type, "\xA9") => $this->userData(substr($type, 1), $bodyStart, $bodyEnd),
                default => null,
            };

            $offset = $bodyEnd;
        }
    }

    private function movieHeader(int $at): void
    {
        $version = ord($this->bytes($at, 1));
        $seconds = $version === 1 ? $this->uint64($this->bytes($at + 4, 8)) : unpack('N', $this->bytes($at + 4, 4))[1];

        // 0 = not set; anything before 2000 is a default value, not a real date.
        if ($seconds > self::EPOCH_OFFSET + 946684800) {
            $this->result['created_at'] = Carbon::createFromTimestampUTC($seconds - self::EPOCH_OFFSET);
        }
    }

    private function handler(int $start, int $end): void
    {
        // version/flags (4) + pre_defined (4) + handler type (4) + reserved (12) + name:
        // a C string (ISO) or a Pascal string (QuickTime, first byte = length).
        $raw = rtrim($this->bytes($start + 24, min(128, $end - $start - 24)), "\0");
        if ($raw !== '' && ord($raw[0]) === strlen($raw) - 1) {
            $raw = substr($raw, 1);
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) @iconv('UTF-8', 'UTF-8//IGNORE', $raw)));

        if ($name !== '' && ! in_array($name, $this->result['handlers'], true)) {
            $this->result['handlers'][] = $name;
        }
    }

    /**
     * ISO "meta" has 4 bytes of version/flags before its children, QuickTime "meta" does not.
     */
    private function meta(int $start, int $end, int $depth): void
    {
        $childStart = $this->bytes($start + 4, 4) === 'hdlr' ? $start : $start + 4;
        $keys = [];
        $offset = $childStart;

        while ($offset + 8 <= $end && ++$this->atoms < self::MAX_ATOMS) {
            ['size' => $size, 'type' => $type] = unpack('Nsize/a4type', $this->bytes($offset, 8));
            if ($size < 8 || $offset + $size > $end) {
                return;
            }

            if ($type === 'keys') {
                $keys = $this->keys($offset + 8, $offset + $size);
            } elseif ($type === 'ilst') {
                $this->itemList($offset + 8, $offset + $size, $keys);
            }

            $offset += $size;
        }
    }

    /**
     * @return array<int, string> 1-based index => key (com.apple.quicktime.make…)
     */
    private function keys(int $start, int $end): array
    {
        $count = unpack('N', $this->bytes($start + 4, 4))[1];
        $offset = $start + 8;
        $keys = [];

        for ($i = 1; $i <= min($count, 200) && $offset + 8 <= $end; $i++) {
            $size = unpack('N', $this->bytes($offset, 4))[1];
            if ($size < 8) {
                break;
            }
            $keys[$i] = $this->bytes($offset + 8, $size - 8);
            $offset += $size;
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function itemList(int $start, int $end, array $keys): void
    {
        $offset = $start;

        while ($offset + 8 <= $end && ++$this->atoms < self::MAX_ATOMS) {
            $header = $this->bytes($offset, 8);
            $size = unpack('N', $header)[1];
            if ($size < 8 || $offset + $size > $end) {
                return;
            }

            $type = substr($header, 4, 4);
            $index = unpack('N', $type)[1];
            $name = $keys[$index] ?? (str_starts_with($type, "\xA9") ? substr($type, 1) : $type);
            $value = $this->dataAtom($offset + 8, $offset + $size);

            if ($value !== null) {
                $this->tag($name, $value);
            }

            $offset += $size;
        }
    }

    private function dataAtom(int $start, int $end): ?string
    {
        if ($end - $start < 16 || $this->bytes($start + 4, 4) !== 'data') {
            return null;
        }

        $size = unpack('N', $this->bytes($start, 4))[1];

        // size (4) + "data" (4) + type (4) + locale (4) + value.
        return $this->bytes($start + 16, max(0, min($size, $end - $start) - 16));
    }

    /**
     * QuickTime user data text: either a "data" atom (iTunes style) or size (2) + language (2) + text.
     */
    private function userData(string $name, int $start, int $end): void
    {
        $value = $this->dataAtom($start, $end);

        if ($value === null && $end - $start > 4) {
            $length = unpack('n', $this->bytes($start, 2))[1];
            $value = $this->bytes($start + 4, min($length, $end - $start - 4));
        }

        if ($value !== null) {
            $this->tag($name, $value);
        }
    }

    private function tag(string $name, string $value): void
    {
        $value = trim((string) @iconv('UTF-8', 'UTF-8//IGNORE', $value), "\x00 \t\n\r");
        $name = strtolower(trim($name));

        if ($value !== '' && $name !== '' && mb_strlen($value) <= 500 && ! isset($this->result['tags'][$name])) {
            $this->result['tags'][$name] = $value;
        }
    }

    private function bytes(int $offset, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        fseek($this->handle, $offset);

        return (string) fread($this->handle, $length);
    }

    private function uint64(string $bytes): int
    {
        ['hi' => $hi, 'lo' => $lo] = unpack('Nhi/Nlo', str_pad($bytes, 8, "\0"));

        return ($hi << 32) | $lo;
    }
}
