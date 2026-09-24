<?php

namespace App\Models;

use App\Data\PreselectionRules;
use App\Enums\PreselectionState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pre-selection round of a competition: registered (paid) artists submit one
 * performance, the public likes (one like per user) and the jury scores, then
 * the organizer publishes the N selected artists.
 */
#[Fillable(['starts_at', 'ends_at', 'vote_ends_at', 'deliberation_hours', 'rules', 'judges_per_entry'])]
class Preselection extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'judges_per_entry' => 'integer',
            'ends_at' => 'datetime',
            'vote_ends_at' => 'datetime',
            'deliberation_hours' => 'integer',
            'published_at' => 'datetime',
            'rules' => PreselectionRules::class,
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
     * @return HasMany<PreselectionSubmission, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PreselectionSubmission::class);
    }

    /**
     * @return HasMany<PreselectionLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PreselectionLike::class);
    }

    /**
     * End of the public likes (never before the end of submissions).
     */
    public function voteEndsAt(): Carbon
    {
        return $this->vote_ends_at !== null && $this->vote_ends_at->greaterThan($this->ends_at) ? $this->vote_ends_at : $this->ends_at;
    }

    /**
     * End of the jury deliberation: the publication is possible from then on.
     */
    public function deliberationEndsAt(): Carbon
    {
        return $this->voteEndsAt()->copy()->addHours((int) $this->deliberation_hours);
    }

    public function state(): PreselectionState
    {
        return match (true) {
            $this->published_at !== null => PreselectionState::Published,
            $this->ends_at->isFuture() => PreselectionState::Open,
            $this->voteEndsAt()->isFuture() => PreselectionState::Voting,
            $this->deliberationEndsAt()->isFuture() => PreselectionState::Deliberation,
            default => PreselectionState::Closed,
        };
    }

    /**
     * Artists can submit (or replace) their performance.
     */
    public function isOpen(): bool
    {
        return $this->state() === PreselectionState::Open;
    }

    /**
     * The public can like: until the end of the vote, when the organizer enabled public voting.
     */
    public function acceptsLikes(): bool
    {
        return $this->publicVotingEnabled() && in_array($this->state(), [PreselectionState::Open, PreselectionState::Voting], true);
    }

    /**
     * Judges can score: from the start until the end of the deliberation.
     */
    public function acceptsScores(): bool
    {
        return in_array($this->state(), [PreselectionState::Open, PreselectionState::Voting, PreselectionState::Deliberation], true);
    }

    /**
     * Entries split between the judges (each scored by judges_per_entry of them), else every judge scores every entry.
     */
    public function splitsJudging(): bool
    {
        return $this->judges_per_entry !== null && $this->judges_per_entry > 0;
    }

    public function publicVotingEnabled(): bool
    {
        return $this->competition->settings->publicVotingEnabled;
    }

    /**
     * Weights used for the ranking: 100 % jury when the public vote is disabled.
     *
     * @return array{jury: int, likes: int}
     */
    public function effectiveWeights(): array
    {
        return $this->publicVotingEnabled()
            ? ['jury' => $this->rules->juryWeight, 'likes' => $this->rules->likeWeight]
            : ['jury' => 100, 'likes' => 0];
    }

    /**
     * Milestones for x-ui.timeline: [label, date, done|current|todo, icon].
     *
     * @return list<array{0: string, 1: ?Carbon, 2: string, 3: string}>
     */
    public function timeline(): array
    {
        $order = [PreselectionState::Scheduled, PreselectionState::Open, PreselectionState::Voting, PreselectionState::Deliberation, PreselectionState::Closed, PreselectionState::Published];
        $position = array_search($this->state(), $order, true);
        $status = fn (PreselectionState $step) => match (true) {
            $position > array_search($step, $order, true) => 'done',
            $position === array_search($step, $order, true) => 'current',
            default => 'todo',
        };

        return array_values(array_filter([
            ['Envois des prestations', $this->ends_at, $status(PreselectionState::Open), 'arrow-up-tray'],
            $this->publicVotingEnabled() ? ['Vote du public', $this->voteEndsAt(), $status(PreselectionState::Voting), 'heart'] : null,
            ['Délibération du jury', $this->deliberationEndsAt(), $status(PreselectionState::Deliberation), 'scale'],
            ['Résultats', $this->published_at, $this->published_at ? 'done' : $status(PreselectionState::Closed), 'trophy'],
        ]));
    }

    /**
     * Next deadline of the timeline, for countdowns.
     */
    public function nextDeadline(): ?Carbon
    {
        return match ($this->state()) {
            PreselectionState::Open => $this->ends_at,
            PreselectionState::Voting => $this->voteEndsAt(),
            PreselectionState::Deliberation => $this->deliberationEndsAt(),
            default => null,
        };
    }

    /**
     * Rules can change until the first performance is sent.
     */
    public function rulesFrozen(): bool
    {
        return $this->exists && $this->entries()->exists();
    }
}
