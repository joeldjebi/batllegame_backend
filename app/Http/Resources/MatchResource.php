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
        // Scores stay hidden until the match is closed (a group: until its phase results are published), unless live results are enabled.
        $showScores = $this->resultsArePublic();
        $media = $this->publishedPerformances()->groupBy('participant_id');

        return [
            'id' => $this->id,
            'phase_id' => $this->phase_id,
            'group_id' => $this->group_id,
            // A group of a ranking round: all its artists perform, the public votes once per phase.
            'is_group' => $this->isGroupMatch(),
            'title' => $this->title(),
            'bracket' => $this->bracket,
            'round' => $this->round,
            'bracket_position' => $this->bracket_position,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'submission_deadline' => $this->submission_deadline,
            'voting_opens_at' => $this->voting_opens_at,
            'voting_closes_at' => $this->voting_closes_at,
            'voting_open' => $this->isVotingOpen(),
            'deliberation_ends_at' => $this->deliberation_ends_at,
            'jury_scoring_open' => $this->acceptsJuryScores(),
            // On-site: the room code displayed on screen must be sent with the vote.
            'vote_code_required' => $this->vote_code !== null,
            'is_forfeit' => $this->is_forfeit,
            'stage' => $this->stage ? ['id' => $this->stage->id, 'name' => $this->stage->name, 'status' => $this->stage->status] : null,
            'winner_id' => $this->winner_id,
            // Web page with a preview, for the share sheet of the app.
            'share_url' => route('fan.competitions.show', $this->competition).'#match-'.$this->id,
            'slots' => $this->whenLoaded('slots', fn () => $this->slots->map(fn (MatchParticipant $slot) => [
                'slot' => $slot->slot,
                'participant' => $slot->participant ? [
                    'id' => $slot->participant->id,
                    'stage_name' => $slot->participant->stage_name,
                    'avatar_url' => $slot->participant->relationLoaded('user') ? $slot->participant->user?->avatarUrl() : null,
                ] : null,
                'jury_score' => $showScores ? $slot->jury_score : null,
                'public_score' => $showScores ? $slot->public_score : null,
                'final_score' => $showScores ? $slot->final_score : null,
                'rank' => $showScores ? $slot->rank : null,
                'is_forfeit' => $slot->is_forfeit,
                'media' => MediaResource::collection($media->get($slot->participant_id, collect())),
            ])),
        ];
    }
}
