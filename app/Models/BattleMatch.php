<?php

namespace App\Models;

use App\Enums\BracketSide;
use App\Enums\MatchStatus;
use App\Enums\PerformanceStatus;
use App\Models\Concerns\InheritsCompetitionId;
use Database\Factories\BattleMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A battle between two participants. Named BattleMatch because "match" is a PHP keyword.
 */
#[Table('matches')]
#[Fillable([
    'phase_id', 'group_id', 'stage_id', 'bracket', 'round', 'bracket_position',
    'next_match_id', 'next_match_slot', 'loser_next_match_id', 'loser_next_match_slot',
    'scheduled_at', 'submission_deadline', 'voting_opens_at', 'voting_closes_at',
])]
class BattleMatch extends Model
{
    /** @use HasFactory<BattleMatchFactory> */
    use HasFactory, InheritsCompetitionId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MatchStatus::Scheduled->value,
    ];

    protected function casts(): array
    {
        return [
            'bracket' => BracketSide::class,
            'status' => MatchStatus::class,
            'round' => 'integer',
            'bracket_position' => 'integer',
            'next_match_slot' => 'integer',
            'loser_next_match_slot' => 'integer',
            'scheduled_at' => 'datetime',
            'submission_deadline' => 'datetime',
            'voting_opens_at' => 'datetime',
            'voting_closes_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_forfeit' => 'boolean',
        ];
    }

    protected function competitionIdSourceKey(): string
    {
        return 'phase_id';
    }

    protected function resolveCompetitionId(): ?int
    {
        return Phase::query()->whereKey($this->phase_id)->value('competition_id');
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Published media of this match's participants for its stage.
     *
     * @return Collection<int, Performance>
     */
    public function publishedPerformances(): Collection
    {
        return Performance::query()
            ->where(fn ($q) => $q->where('match_id', $this->id)->orWhere(fn ($q) => $q->whereNull('match_id')->where('stage_id', $this->stage_id)))
            ->whereIn('participant_id', $this->slots()->whereNotNull('participant_id')->select('participant_id'))
            ->where('status', PerformanceStatus::Approved)
            ->get();
    }

    /**
     * @return BelongsTo<BattleMatch, $this>
     */
    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    /**
     * @return BelongsTo<BattleMatch, $this>
     */
    public function loserNextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_next_match_id');
    }

    /**
     * Matches whose winner feeds into this one.
     *
     * @return HasMany<BattleMatch, $this>
     */
    public function feederMatches(): HasMany
    {
        return $this->hasMany(self::class, 'next_match_id');
    }

    /**
     * The two slots of the match, ordered by slot.
     *
     * @return HasMany<MatchParticipant, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(MatchParticipant::class, 'match_id')->orderBy('slot');
    }

    /**
     * @return BelongsToMany<Participant, $this, MatchParticipant, 'slot'>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class, 'match_participants', 'match_id')
            ->using(MatchParticipant::class)
            ->as('slot')
            ->withPivot('id', 'slot', 'jury_score', 'public_score', 'final_score')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'winner_id');
    }

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class, 'match_id');
    }

    /**
     * @return HasMany<JuryScore, $this>
     */
    public function juryScores(): HasMany
    {
        return $this->hasMany(JuryScore::class, 'match_id');
    }

    /**
     * @return HasMany<PublicVote, $this>
     */
    public function publicVotes(): HasMany
    {
        return $this->hasMany(PublicVote::class, 'match_id');
    }

    /**
     * Matches whose vote is open right now (the scheduler may not have closed expired ones yet).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function votingNow(Builder $query): void
    {
        $query->where('status', MatchStatus::Voting)
            ->where(fn ($q) => $q->whereNull('voting_closes_at')->orWhere('voting_closes_at', '>', now()));
    }

    public function isClosed(): bool
    {
        return $this->status === MatchStatus::Closed;
    }

    public function isGroupMatch(): bool
    {
        return $this->group_id !== null;
    }

    public function isVotingOpen(): bool
    {
        return $this->status === MatchStatus::Voting
            && ($this->voting_opens_at === null || $this->voting_opens_at->isPast())
            && ($this->voting_closes_at === null || $this->voting_closes_at->isFuture());
    }
}
