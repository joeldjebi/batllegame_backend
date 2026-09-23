<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsToMany<Participant, $this, GroupParticipant, 'standing'>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class, 'group_participants')
            ->using(GroupParticipant::class)
            ->as('standing')
            ->withPivot('id', 'points', 'wins', 'draws', 'losses', 'score_diff', 'rank')
            ->withTimestamps();
    }

    /**
     * Standings rows, ordered by rank (unranked last).
     *
     * @return HasMany<GroupParticipant, $this>
     */
    public function standings(): HasMany
    {
        return $this->hasMany(GroupParticipant::class)
            ->orderByRaw('rank IS NULL')
            ->orderBy('rank');
    }

    /**
     * @return HasMany<BattleMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(BattleMatch::class);
    }
}
