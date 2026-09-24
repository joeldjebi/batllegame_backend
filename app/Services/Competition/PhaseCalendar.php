<?php

namespace App\Services\Competition;

use App\Enums\PhaseType;
use App\Models\Phase;
use Illuminate\Support\Carbon;

/**
 * The stages a phase will have, known before it starts (« Poules », then « Quarts de finale »,
 * « Demi-finales », « Finale » for the bracket sized by the expected entrants), and their planned
 * dates. At launch the dates are copied to the real stages, matched by name: if the bracket turns
 * out smaller, the last rounds (demi-finales, finale) still get their dates.
 */
class PhaseCalendar
{
    public const array FIELDS = ['submission_deadline', 'voting_opens_at', 'voting_closes_at', 'deliberation_minutes'];

    /**
     * @return list<string> Stage names in playing order (empty: planned after the start).
     */
    public static function stageNames(Phase $phase): array
    {
        if ($phase->type === PhaseType::Groups) {
            return ['Poules'];
        }

        // Double elimination: winners and losers rounds interleave, planned once generated.
        if ($phase->type !== PhaseType::SingleElimination) {
            return [];
        }

        $entrants = self::expectedEntrants($phase);
        if ($entrants === null || $entrants < 2) {
            return [];
        }

        $rounds = (int) ceil(log($entrants, 2));

        return array_map(fn (int $round) => RoundLabel::for(null, $round, $rounds), range(1, $rounds));
    }

    /**
     * Entrants of the phase: the qualifiers of the previous group phase, else the planned size.
     */
    public static function expectedEntrants(Phase $phase): ?int
    {
        return $phase->rules->expectedEntrants ?? GroupPlan::expectedEntrants($phase->competition, $phase);
    }

    /**
     * Planned stages with their dates (Carbon, competition time zone applied on input).
     *
     * @return list<array{name: string, submission_deadline: ?Carbon, voting_opens_at: ?Carbon, voting_closes_at: ?Carbon, deliberation_minutes: ?int}>
     */
    public static function planned(Phase $phase): array
    {
        $calendar = (array) $phase->calendar;

        return array_map(function (string $name) use ($calendar): array {
            $dates = (array) ($calendar[$name] ?? []);

            return [
                'name' => $name,
                'submission_deadline' => isset($dates['submission_deadline']) ? Carbon::parse($dates['submission_deadline']) : null,
                'voting_opens_at' => isset($dates['voting_opens_at']) ? Carbon::parse($dates['voting_opens_at']) : null,
                'voting_closes_at' => isset($dates['voting_closes_at']) ? Carbon::parse($dates['voting_closes_at']) : null,
                'deliberation_minutes' => isset($dates['deliberation_minutes']) ? (int) $dates['deliberation_minutes'] : null,
            ];
        }, self::stageNames($phase));
    }

    /**
     * At launch: copy the planned dates onto the stages just built.
     */
    public function apply(Phase $phase, StageService $stages): void
    {
        $calendar = (array) $phase->calendar;

        foreach ($phase->stages()->get() as $stage) {
            $dates = array_filter((array) ($calendar[$stage->name] ?? []), fn ($value) => $value !== null && $value !== '');

            if ($dates !== []) {
                $stages->schedule($stage, $dates);
            }
        }
    }
}
