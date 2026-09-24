<?php

namespace App\Models;

use App\Support\Locations;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'iso2', 'iso3', 'dial_code', 'phone_min_length', 'phone_max_length', 'phone_example', 'flag', 'currency_code', 'is_active', 'position'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => Locations::forget());
        static::deleted(fn () => Locations::forget());
    }

    protected function casts(): array
    {
        return [
            'phone_min_length' => 'integer',
            'phone_max_length' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Countries offered in phone number selectors.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('name');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Strip everything but digits from a national number typed by a user.
     */
    public function cleanNationalNumber(string $national): string
    {
        return preg_replace('/\D+/', '', $national) ?? '';
    }

    public function isValidNationalNumber(string $national): bool
    {
        $length = strlen($this->cleanNationalNumber($national));

        return $length >= $this->phone_min_length && $length <= $this->phone_max_length;
    }

    /**
     * Build the E.164 number stored in users.phone, e.g. "+2250701020304".
     */
    public function toE164(string $national): string
    {
        return $this->dial_code.$this->cleanNationalNumber($national);
    }
}
