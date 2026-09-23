<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One of the two slots of a match. Scores are denormalized (0-100) and
 * recomputable from jury_scores and public_votes.
 */
#[Table('match_participants', incrementing: true)]
#[Fillable(['match_id', 'participant_id', 'slot', 'jury_score', 'public_score', 'final_score'])]
class MatchParticipant extends Pivot
{
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'jury_score' => 'float',
            'public_score' => 'float',
            'final_score' => 'float',
        ];
    }

    /**
     * @return BelongsTo<BattleMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(BattleMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
