<?php

namespace App\Models\Concerns;

use App\Models\City;
use App\Models\Commune;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reference place (city, optional commune) picked from the super-admin's lists.
 */
trait HasLocation
{
    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * « Cocody, Abidjan », « Bouaké » or null.
     */
    public function locationLabel(): ?string
    {
        return collect([$this->commune?->name, $this->city?->name])->filter()->join(', ') ?: null;
    }

    /**
     * @return ?array{city: array{id: int, name: string}, commune: ?array{id: int, name: string}, label: string}
     */
    public function locationData(): ?array
    {
        if ($this->city === null) {
            return null;
        }

        return [
            'city' => ['id' => $this->city->id, 'name' => $this->city->name],
            'commune' => $this->commune ? ['id' => $this->commune->id, 'name' => $this->commune->name] : null,
            'label' => $this->locationLabel(),
        ];
    }
}
