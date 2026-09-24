<?php

namespace App\Models;

use App\Enums\OrganizerPlan;
use App\Enums\OrganizerStatus;
use App\Enums\SeedKind;
use App\Models\Concerns\HasLocation;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\OrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// status, verified_at and plan are managed by the platform admin only, hence not fillable.
#[RouteKey('slug')]
#[Fillable(['name', 'slug', 'logo_path', 'description', 'city_id', 'commune_id'])]
class Organizer extends Model
{
    /** @use HasFactory<OrganizerFactory> */
    use HasFactory, HasLocation, HasUniqueSlug;

    /**
     * Mirrors the column defaults so enum-based helpers work before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrganizerStatus::Pending->value,
        'plan' => OrganizerPlan::Free->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizerStatus::class,
            'seed_kind' => SeedKind::class,
            'plan' => OrganizerPlan::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrganizerMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizerMember::class);
    }

    /**
     * @return BelongsToMany<User, $this, OrganizerMember, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizer_members')
            ->using(OrganizerMember::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public function isVerified(): bool
    {
        return $this->status === OrganizerStatus::Verified;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizerStatus::Suspended;
    }
}
