<?php

namespace App\Models;

use App\Data\CompetitionSettings;
use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * organizer_id and created_by are set explicitly by the application, never mass assigned.
 */
#[Fillable(['name', 'slug', 'discipline', 'mode', 'status', 'registration_ends_at', 'max_participants', 'entry_fee', 'currency', 'settings'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirrors the column defaults so enum-based helpers work before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => CompetitionStatus::Draft->value,
    ];

    protected function casts(): array
    {
        return [
            'discipline' => Discipline::class,
            'mode' => CompetitionMode::class,
            'status' => CompetitionStatus::class,
            'registration_ends_at' => 'datetime',
            'max_participants' => 'integer',
            'entry_fee' => 'integer',
            'settings' => CompetitionSettings::class,
        ];
    }

    /**
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('position');
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * @return HasMany<Judge, $this>
     */
    public function judges(): HasMany
    {
        return $this->hasMany(Judge::class);
    }

    /**
     * @return HasMany<Criterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('position');
    }

    /**
     * @return HasMany<BattleMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BattleMatch::class);
    }

    /**
     * @return HasMany<JuryScore, $this>
     */
    public function juryScores(): HasMany
    {
        return $this->hasMany(JuryScore::class);
    }

    /**
     * @return HasMany<PublicVote, $this>
     */
    public function publicVotes(): HasMany
    {
        return $this->hasMany(PublicVote::class);
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === CompetitionStatus::Registration
            && ($this->registration_ends_at === null || $this->registration_ends_at->isFuture());
    }
}
