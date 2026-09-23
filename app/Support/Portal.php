<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The web portals for mobile-type accounts (phone + password), each with its
 * own login URL, until the Flutter app ships.
 */
final readonly class Portal
{
    public function __construct(
        public string $key,
        public string $guard,
        public string $title,
        public string $tagline,
        public string $icon,
        public string $home,
        public bool $canRegister,
    ) {}

    public static function get(string $key): self
    {
        return match ($key) {
            'jury' => new self('jury', 'jury', 'Espace jury', 'Notez les battles des compétitions qui vous sont confiées.', 'scale', 'jury.dashboard', false),
            'artist' => new self('artist', 'member', 'Espace artiste', 'Inscrivez-vous aux compétitions et envoyez vos prestations.', 'microphone', 'artist.dashboard', true),
            'fan' => new self('fan', 'member', 'Espace public', 'Regardez les battles et votez pour vos artistes préférés.', 'hand-thumb-up', 'fan.dashboard', true),
            default => throw new InvalidArgumentException("Unknown portal [{$key}]."),
        };
    }

    /**
     * Portal of the current route ("jury.*", "artist.*" or "fan.*").
     */
    public static function current(): self
    {
        return self::get(explode('.', (string) request()->route()?->getName())[0]);
    }

    public function route(string $name, mixed $parameters = []): string
    {
        return route($this->key.'.'.$name, $parameters);
    }
}
