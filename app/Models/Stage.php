<?php

namespace App\Models;

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\StageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Matches played at the same time within a phase (the whole phase for groups,
 * one bracket round for elimination). One submission per participant and stage.
 */
#[Fillable(['number', 'name', 'submission_deadline', 'voting_opens_at', 'voting_closes_at', 'deliberation_minutes'])]
class Stage extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => StageStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => StageStatus::class,
            'submission_deadline' => 'datetime',
            'voting_opens_at' => 'datetime',
            'voting_closes_at' => 'datetime',
            'deliberation_minutes' => 'integer',
            'forfeits_applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return HasMany<BattleMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BattleMatch::class);
    }

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    /**
     * Matches with two participants that still have to be played.
     *
     * @return HasMany<BattleMatch, $this>
     */
    public function playableMatches(): HasMany
    {
        return $this->matches()
            ->whereIn('status', [MatchStatus::Scheduled, MatchStatus::Submissions, MatchStatus::Voting])
            ->whereDoesntHave('slots', fn (Builder $q) => $q->whereNull('participant_id'));
    }

    /**
     * Participant ids that play at least one playable match of this stage.
     *
     * @return list<int>
     */
    public function participantIds(): array
    {
        return MatchParticipant::query()
            ->whereIn('match_id', $this->playableMatches()->select('matches.id'))
            ->whereNotNull('participant_id')
            ->distinct()
            ->pluck('participant_id')
            ->all();
    }

    /**
     * The participant of this user playing a match of this stage, if any.
     */
    public function participantFor(User $user): ?Participant
    {
        $participant = Participant::query()
            ->where('competition_id', $this->phase->competition_id)
            ->where('user_id', $user->id)
            ->first();

        return $participant && in_array($participant->id, $this->participantIds(), true) ? $participant : null;
    }

    /**
     * Online stages collect submissions; on-site stages are played live.
     */
    public function isOnline(): bool
    {
        return $this->phase->effectiveMode() === CompetitionMode::Online;
    }

    /**
     * End of the jury deliberation for a vote closing at $votingClosesAt (null = manual close).
     */
    public function deliberationEndFor(?Carbon $votingClosesAt): ?Carbon
    {
        return $votingClosesAt && $this->deliberation_minutes > 0 ? $votingClosesAt->copy()->addMinutes($this->deliberation_minutes) : null;
    }

    public function isDeadlinePassed(): bool
    {
        return $this->submission_deadline !== null && $this->submission_deadline->isPast();
    }

    public function acceptsSubmissions(): bool
    {
        return $this->status === StageStatus::Submissions && ! $this->isDeadlinePassed();
    }
}
