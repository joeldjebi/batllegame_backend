<?php

namespace App\Services\Competition;

use App\Enums\BracketSide;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Models\BattleMatch;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;

/**
 * Moves participants through an elimination bracket once a match is decided.
 *
 * A target match resolves itself once all its feeders are decided:
 *  - two participants: playable, it waits to be played;
 *  - one participant (bye): walkover, the participant advances;
 *  - nobody: the match is void (cancelled) and the emptiness propagates.
 */
class BracketAdvancer
{
    /**
     * Push the winner (and, in double elimination, the loser) of a decided match.
     */
    public function advance(BattleMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            // Always reload: slots may have been filled since the model was fetched.
            $match->load('slots');

            $winnerId = $match->winner_id;
            $loserId = $winnerId === null
                ? null
                : $match->slots->first(fn ($slot) => $slot->participant_id !== null && $slot->participant_id !== $winnerId)?->participant_id;

            if ($this->isDecidedResetFinal($match, $winnerId)) {
                $this->voidMatch(BattleMatch::query()->findOrFail($match->next_match_id));
                $this->eliminate($loserId);

                return;
            }

            if ($match->next_match_id !== null && $winnerId !== null) {
                $this->placeInSlot($match->next_match_id, $match->next_match_slot, $winnerId);
            }

            if ($match->loser_next_match_id !== null) {
                if ($loserId !== null) {
                    $this->placeInSlot($match->loser_next_match_id, $match->loser_next_match_slot, $loserId);
                }
            } else {
                $this->eliminate($loserId);
            }

            foreach (array_filter([$match->next_match_id, $match->loser_next_match_id]) as $targetId) {
                $this->resolveIfReady(BattleMatch::query()->findOrFail($targetId));
            }
        });
    }

    /**
     * Resolve a match automatically when its feeders are all decided and it
     * cannot be played (bye or empty). Safe to call several times.
     */
    public function resolveIfReady(BattleMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = BattleMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($match->status !== MatchStatus::Scheduled) {
                return;
            }

            $pendingFeeders = BattleMatch::query()
                ->where(fn ($q) => $q->where('next_match_id', $match->id)->orWhere('loser_next_match_id', $match->id))
                ->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])
                ->exists();

            if ($pendingFeeders) {
                return;
            }

            $present = $match->slots()->whereNotNull('participant_id')->pluck('participant_id');

            if ($present->count() === 2) {
                return;
            }

            if ($present->count() === 1) {
                $match->forceFill([
                    'status' => MatchStatus::Closed,
                    'winner_id' => $present->first(),
                    'closed_at' => now(),
                ])->save();
            } else {
                $match->forceFill(['status' => MatchStatus::Cancelled, 'closed_at' => now()])->save();
            }

            $this->advance($match);
        });
    }

    /**
     * First grand final won by the winners-bracket champion (slot 1): no reset needed.
     */
    private function isDecidedResetFinal(BattleMatch $match, ?int $winnerId): bool
    {
        return $match->bracket === BracketSide::GrandFinal
            && $match->round === 1
            && $match->next_match_id !== null
            && $winnerId !== null
            && $match->slots->firstWhere('slot', 1)?->participant_id === $winnerId;
    }

    private function voidMatch(BattleMatch $match): void
    {
        if ($match->status === MatchStatus::Scheduled) {
            $match->forceFill(['status' => MatchStatus::Cancelled, 'closed_at' => now()])->save();
        }
    }

    private function placeInSlot(int $matchId, int $slot, int $participantId): void
    {
        BattleMatch::query()->lockForUpdate()->findOrFail($matchId)
            ->slots()->where('slot', $slot)->update(['participant_id' => $participantId]);
    }

    private function eliminate(?int $participantId): void
    {
        if ($participantId !== null) {
            Participant::query()->whereKey($participantId)->update(['status' => ParticipantStatus::Eliminated]);
        }
    }
}
