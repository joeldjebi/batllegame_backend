<?php

namespace App\Data;

use App\Casts\JsonDataCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ValidationException;
use JsonSerializable;

/**
 * Base class for immutable, validated value objects stored in JSON columns.
 *
 * Subclasses validate their input in fromArray() and must be able to rebuild
 * themselves from their own toArray() output.
 *
 * @implements Arrayable<string, mixed>
 */
abstract readonly class JsonData implements Arrayable, Castable, JsonSerializable
{
    /**
     * Build a validated instance from raw input.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    abstract public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Instance used when the column is null.
     */
    public static function defaults(): static
    {
        return static::fromArray([]);
    }

    /**
     * Return a new validated instance with the given attributes changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): static
    {
        return static::fromArray(array_replace($this->toArray(), $changes));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): JsonDataCast
    {
        return new JsonDataCast(static::class);
    }
}
