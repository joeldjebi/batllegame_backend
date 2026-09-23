<?php

namespace App\Casts;

use App\Data\JsonData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a JSON column to a JsonData value object and back.
 *
 * @template TData of JsonData
 *
 * @implements CastsAttributes<TData, TData|array<string, mixed>|null>
 */
class JsonDataCast implements CastsAttributes
{
    /**
     * @param  class-string<TData>  $class
     */
    public function __construct(private string $class) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return TData
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): JsonData
    {
        if ($value === null || $value === '') {
            return $this->class::defaults();
        }

        return $this->class::fromArray(json_decode($value, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $this->class::fromArray($value);
        }

        if (! $value instanceof $this->class) {
            throw new InvalidArgumentException("The [{$key}] attribute must be an instance of {$this->class} or an array.");
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
