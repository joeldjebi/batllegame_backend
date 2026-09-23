<?php

namespace App\Models;

use App\Models\Concerns\InheritsCompetitionId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source of truth for jury scores; match_participants.jury_score is derived from it.
 */
#[Fillable(['match_id', 'judge_id', 'participant_id', 'criterion_id', 'score', 'comment'])]
class JuryScore extends Model
{
    use InheritsCompetitionId;

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    protected function competitionIdSourceKey(): string
    {
        return 'match_id';
    }

    protected function resolveCompetitionId(): ?int
    {
        return BattleMatch::query()->whereKey($this->match_id)->value('competition_id');
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<BattleMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(BattleMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<Judge, $this>
     */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(Judge::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<Criterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
