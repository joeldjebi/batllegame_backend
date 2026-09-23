<?php

namespace App\Models;

use App\Models\Concerns\InheritsCompetitionId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source of truth for public scores; match_participants.public_score is derived from it.
 * user_id, device_id and ip are set by the voting service, never mass assigned.
 */
#[Fillable(['match_id', 'participant_id'])]
#[Hidden(['device_id', 'ip'])]
class PublicVote extends Model
{
    use InheritsCompetitionId;

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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
