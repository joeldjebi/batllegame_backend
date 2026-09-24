<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A slot of a match: one of the two sides of a battle, or one artist of a group
 * (ranking round). Scores are denormalized (0-100) and recomputable from
 * jury_scores and public_votes.
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
            'is_forfeit' => 'boolean',
            'rank' => 'integer',
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
