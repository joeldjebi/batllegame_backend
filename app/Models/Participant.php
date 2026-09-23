<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use App\Enums\StageStatus;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'stage_name', 'seed', 'status'])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults so enum-based helpers work before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ParticipantStatus::Registered->value,
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'status' => ParticipantStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Group, $this, GroupParticipant, 'standing'>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_participants')
            ->using(GroupParticipant::class)
            ->as('standing')
            ->withPivot('id', 'points', 'wins', 'draws', 'losses', 'score_diff', 'rank')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MatchParticipant, $this>
     */
    public function matchSlots(): HasMany
    {
        return $this->hasMany(MatchParticipant::class);
    }

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    /**
     * The earliest stage (not closed) where this participant has a match.
     */
    public function currentStage(): ?Stage
    {
        return Stage::query()
            ->where('status', '!=', StageStatus::Closed)
            ->whereHas('matches.slots', fn ($q) => $q->where('participant_id', $this->id))
            ->whereHas('phase', fn ($q) => $q->where('competition_id', $this->competition_id))
            ->with('phase.competition')
            ->orderBy('phase_id')->orderBy('number')
            ->first();
    }

    /**
     * Still competing (registered or validated, not eliminated or out).
     */
    public function isActive(): bool
    {
        return in_array($this->status, [ParticipantStatus::Registered, ParticipantStatus::Validated], true);
    }
}
