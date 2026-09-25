<?php

namespace App\Services\Competition;

use App\Enums\ParticipantStatus;
use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Phase;

/**
 * Arithmetic of a group phase: how N entrants split into G groups (snake draw)
 * and whether Q qualifiers per group makes sense. The back-office preview
 * (resources/js/app.js, groupPlanner) mirrors these rules.
 */
class GroupPlan
{
    /**
     * Group sizes, biggest first: 10 entrants in 3 groups => [4, 3, 3].
     *
     * @return list<int>
     */
    public static function sizes(int $entrants, int $groups): array
    {
        if ($groups < 1) {
            return [];
        }

        $base = intdiv($entrants, $groups);
        $extra = $entrants % $groups;

        return array_map(fn (int $i) => $base + ($i < $extra ? 1 : 0), range(0, $groups - 1));
    }

    /**
     * Blocking problems, in French (empty when the format is playable).
     *
     * @return list<string>
     */
    public static function problems(int $entrants, int $groups, int $qualifiers): array
    {
        if ($groups < 1 || $qualifiers < 1) {
            return ['Au moins une poule et un qualifié par poule.'];
        }

        $maxGroups = intdiv($entrants, 2);
        if ($groups > $maxGroups) {
            return [$maxGroups < 1
                ? "Il faut au moins 2 participants (actuellement {$entrants})."
                : "Trop de poules pour {$entrants} participants : {$maxGroups} au maximum (2 artistes minimum par poule)."];
        }

        $smallest = min(self::sizes($entrants, $groups));
        if ($qualifiers >= $smallest) {
            return ["Trop de qualifiés : la plus petite poule compte {$smallest} artistes, qualifiez-en ".($smallest - 1).' au maximum par poule.'];
        }

        return [];
    }

    /**
     * The playable format closest to the planned one, for the real entrants: the planned
     * group size is kept (fewer groups when fewer artists came), then the qualifiers
     * are capped below the smallest group. Null when nothing can be played (< 2 entrants).
     *
     * @return array{groups: int, qualifiers: int}|null
     */
    public static function fit(int $entrants, int $groups, int $qualifiers, ?int $planned = null): ?array
    {
        $groups = max(1, $groups);
        $qualifiers = max(1, $qualifiers);

        if (self::problems($entrants, $groups, $qualifiers) === []) {
            return ['groups' => $groups, 'qualifiers' => $qualifiers];
        }

        if ($entrants < 2) {
            return null;
        }

        $plannedSize = $planned ? (int) round($planned / $groups) : 0;
        $size = max(2, $qualifiers + 1, $plannedSize);
        $groups = max(1, min($groups, intdiv($entrants, 2), (int) round($entrants / $size)));
        $qualifiers = max(1, min($qualifiers, min(self::sizes($entrants, $groups)) - 1));

        return ['groups' => $groups, 'qualifiers' => $qualifiers];
    }

    /**
     * Entrants expected in a new phase, to size its groups before registrations close:
     * the pre-selection size, otherwise the qualifiers of the previous group phase,
     * the maximum of participants or the validated participants.
     */
    public static function expectedEntrants(Competition $competition, ?Phase $phase = null): ?int
    {
        // Editing a phase: what comes before it; a new phase: the last one.
        $previous = $competition->phases()
            ->when($phase, fn ($q) => $q->where('position', '<', $phase->position))
            ->reorder('position', 'desc')
            ->first();

        if ($previous?->type === PhaseType::Groups && $previous->rules->groupCount) {
            return $previous->rules->groupCount * ($previous->qualifiers_per_group ?? 1);
        }

        if ($previous === null && $competition->preselection) {
            return $competition->preselection->rules->selectionSize;
        }

        $validated = $competition->participants()->where('status', ParticipantStatus::Validated)->count();

        return $competition->max_participants ?: ($validated ?: null);
    }
}
