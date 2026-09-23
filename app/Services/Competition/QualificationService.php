<?php

namespace App\Services\Competition;

use App\Enums\ParticipantStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Models\Participant;
use App\Models\Phase;
use Illuminate\Support\Collection;

/**
 * Qualifies the top N of each group of a finished group phase.
 */
class QualificationService
{
    /**
     * Qualifiers in seeding order for the next phase: all group winners
     * (group A, B, C...), then all runners-up, etc. With the standard bracket
     * seeding this crosses the groups (1A vs 2B, 1B vs 2A...).
     *
     * @return Collection<int, Participant>
     */
    public function qualifiers(Phase $phase): Collection
    {
        if ($phase->type !== PhaseType::Groups) {
            throw CompetitionFlowException::unsupportedPreviousPhase();
        }

        $perGroup = $phase->qualifiers_per_group ?? 1;
        $groups = $phase->groups()->with(['standings' => fn ($q) => $q->with('participant')])->get();

        $qualifiers = collect();

        for ($rank = 1; $rank <= $perGroup; $rank++) {
            foreach ($groups as $group) {
                $standing = $group->standings->firstWhere('rank', $rank);

                if ($standing !== null) {
                    $qualifiers->push($standing->participant);
                }
            }
        }

        return $qualifiers;
    }

    /**
     * Mark everyone who did not qualify as eliminated.
     */
    public function eliminateNonQualifiers(Phase $phase): void
    {
        $qualifiedIds = $this->qualifiers($phase)->pluck('id');

        Participant::query()
            ->whereIn('id', fn ($q) => $q->select('participant_id')
                ->from('group_participants')
                ->whereIn('group_id', $phase->groups()->select('id')))
            ->whereNotIn('id', $qualifiedIds)
            ->update(['status' => ParticipantStatus::Eliminated]);
    }
}
