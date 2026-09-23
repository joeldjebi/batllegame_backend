<?php

/**
 * Builds a small but valid MP4 / MOV file with the given hidden metadata.
 *
 * @param  array{brand?: string, created?: ?DateTimeInterface, handler?: ?string, pascal?: bool, udta?: array<string, string>, keys?: array<string, string>}  $spec
 */
function fakeMp4(array $spec = []): string
{
    $atom = fn (string $type, string $payload) => pack('N', 8 + strlen($payload)).$type.$payload;
    $seconds = isset($spec['created']) && $spec['created'] ? $spec['created']->getTimestamp() + 2082844800 : 0;

    $mvhd = $atom('mvhd', "\0\0\0\0".pack('N', $seconds).pack('N', $seconds).pack('N', 600).pack('N', 6000).str_repeat("\0", 80));

    $hdlrName = $spec['handler'] ?? null;
    $trak = '';
    if ($hdlrName !== null) {
        $name = ($spec['pascal'] ?? false) ? chr(strlen($hdlrName)).$hdlrName : $hdlrName."\0";
        $trak = $atom('trak', $atom('mdia', $atom('hdlr', "\0\0\0\0".str_repeat("\0", 4).'vide'.str_repeat("\0", 12).$name)));
    }

    $udta = '';
    foreach ($spec['udta'] ?? [] as $key => $value) {
        $udta .= $atom("\xA9".$key, pack('n', strlen($value)).pack('n', 0).$value);
    }
    $udta = $udta !== '' ? $atom('udta', $udta) : '';

    $meta = '';
    if (! empty($spec['keys'])) {
        $keys = '';
        $items = '';
        $i = 0;
        foreach ($spec['keys'] as $key => $value) {
            $i++;
            $keys .= pack('N', 8 + strlen($key)).'mdta'.$key;
            $items .= $atom(pack('N', $i), $atom('data', pack('N', 1).pack('N', 0).$value));
        }
        $meta = $atom('meta', $atom('hdlr', "\0\0\0\0\0\0\0\0mdta".str_repeat("\0", 12)."\0")
            .$atom('keys', "\0\0\0\0".pack('N', $i).$keys)
            .$atom('ilst', $items));
    }

    $file = $atom('ftyp', str_pad($spec['brand'] ?? 'isom', 4).pack('N', 0).'isommp41')
        .$atom('moov', $mvhd.$trak.$udta.$meta)
        .$atom('mdat', str_repeat("\0", 64));

    $path = tempnam(sys_get_temp_dir(), 'mp4');
    file_put_contents($path, $file);

    return $path;
}
