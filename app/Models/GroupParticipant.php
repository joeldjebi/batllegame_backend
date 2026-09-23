<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Denormalized group standing of a participant, recomputed from closed group matches.
 */
#[Table('group_participants', incrementing: true)]
#[Fillable(['group_id', 'participant_id', 'points', 'wins', 'draws', 'losses', 'score_diff', 'rank'])]
class GroupParticipant extends Pivot
{
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'wins' => 'integer',
            'draws' => 'integer',
            'losses' => 'integer',
            'score_diff' => 'float',
            'rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
