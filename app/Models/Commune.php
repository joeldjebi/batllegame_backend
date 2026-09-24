<?php

namespace App\Models;

use App\Support\Locations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reference commune (district) of a city, managed by the super-admin.
 */
#[Fillable(['name', 'is_active', 'position'])]
class Commune extends Model
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
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function usageCount(): int
    {
        return Organizer::query()->where('commune_id', $this->id)->count()
            + Competition::query()->withTrashed()->where('commune_id', $this->id)->count()
            + User::query()->where('commune_id', $this->id)->count();
    }
}
