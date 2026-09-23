<?php

namespace App\Models;

use App\Enums\OrganizerRole;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'country_id', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<OrganizerMember, $this>
     */
    public function organizerMemberships(): HasMany
    {
        return $this->hasMany(OrganizerMember::class);
    }

    /**
     * @return BelongsToMany<Organizer, $this, OrganizerMember, 'membership'>
     */
    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class, 'organizer_members')
            ->using(OrganizerMember::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * @return HasMany<Judge, $this>
     */
    public function judgeAssignments(): HasMany
    {
        return $this->hasMany(Judge::class);
    }

    /**
     * @return HasMany<PublicVote, $this>
     */
    public function publicVotes(): HasMany
    {
        return $this->hasMany(PublicVote::class);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill(['phone_verified_at' => $this->freshTimestamp()])->save();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasRole(PlatformRole::Admin->value);
    }

    /**
     * Role of this user inside the given organizer, or null if not a member.
     */
    public function roleIn(Organizer|int $organizer): ?OrganizerRole
    {
        $organizerId = $organizer instanceof Organizer ? $organizer->getKey() : $organizer;

        $role = $this->relationLoaded('organizerMemberships')
            ? $this->organizerMemberships->firstWhere('organizer_id', $organizerId)?->role
            : $this->organizerMemberships()->where('organizer_id', $organizerId)->value('role');

        return $role instanceof OrganizerRole ? $role : OrganizerRole::tryFrom((string) $role);
    }

    public function isMemberOf(Organizer|int $organizer): bool
    {
        return $this->roleIn($organizer) !== null;
    }
}
