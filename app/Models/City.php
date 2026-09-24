<?php

namespace App\Models;

use App\Support\Locations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reference city of a country, managed by the super-admin.
 */
#[Fillable(['name', 'is_active', 'position'])]
class City extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Locations::forget());
        static::deleted(fn () => Locations::forget());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('name');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<Commune, $this>
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    /**
     * Organizers, competitions and users placed in this city (a used city is disabled, not deleted).
     */
    public function usageCount(): int
    {
        return Organizer::query()->where('city_id', $this->id)->count()
            + Competition::query()->withTrashed()->where('city_id', $this->id)->count()
            + User::query()->where('city_id', $this->id)->count();
    }
}
