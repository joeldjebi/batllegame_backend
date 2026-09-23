<?php

namespace App\Models;

use App\Data\CompetitionSettings;
use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\ParticipantStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * organizer_id and created_by are set explicitly by the application, never mass assigned.
 */
#[Fillable(['name', 'slug', 'discipline', 'mode', 'status', 'registration_ends_at', 'max_participants', 'entry_fee', 'currency', 'settings'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, HasUniqueSlug, SoftDeletes;

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
     * @return HasManyThrough<Stage, Phase, $this>
     */
    public function stages(): HasManyThrough
    {
        return $this->hasManyThrough(Stage::class, Phase::class);
    }

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    /**
     * @return HasOne<Preselection, $this>
     */
    public function preselection(): HasOne
    {
        return $this->hasOne(Preselection::class);
    }

    /**
     * Pre-selection entries (route parameter {entry}).
     *
     * @return HasMany<PreselectionSubmission, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PreselectionSubmission::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Status of a new participant once the registration fee (if any) is paid:
     * a pre-selection or a manual approval keeps them « inscrit ».
     */
    public function participantStatusAfterRegistration(): ParticipantStatus
    {
        return $this->preselection()->exists() || $this->settings->registrationRequiresApproval
            ? ParticipantStatus::Registered
            : ParticipantStatus::Validated;
    }

    public function requiresPayment(): bool
    {
        return $this->entry_fee > 0;
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
