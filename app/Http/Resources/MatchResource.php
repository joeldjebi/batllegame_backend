<?php

namespace App\Http\Resources;

use App\Models\BattleMatch;
use App\Models\MatchParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BattleMatch
 */
class MatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Scores stay hidden until the match is closed, unless live results are enabled.
        $showScores = $this->isClosed() || $this->competition->settings->showLiveResults;

        return [
            'id' => $this->id,
            'phase_id' => $this->phase_id,
            'group_id' => $this->group_id,
            'bracket' => $this->bracket,
            'round' => $this->round,
            'bracket_position' => $this->bracket_position,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'submission_deadline' => $this->submission_deadline,
            'voting_opens_at' => $this->voting_opens_at,
            'voting_closes_at' => $this->voting_closes_at,
            'voting_open' => $this->isVotingOpen(),
            'winner_id' => $this->winner_id,
            'slots' => $this->whenLoaded('slots', fn () => $this->slots->map(fn (MatchParticipant $slot) => [
                'slot' => $slot->slot,
                'participant' => $slot->participant ? [
                    'id' => $slot->participant->id,
                    'stage_name' => $slot->participant->stage_name,
                ] : null,
                'jury_score' => $showScores ? $slot->jury_score : null,
                'public_score' => $showScores ? $slot->public_score : null,
                'final_score' => $showScores ? $slot->final_score : null,
            ])),
        ];
    }
}
