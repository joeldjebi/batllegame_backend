<?php

namespace App\Services\Competition;

use App\Enums\PhaseType;
use App\Models\Competition;
use App\Models\Phase;

/**
 * Adds a phase at the end of a competition. Groups only qualify artists, so a group
 * phase brings along the single-elimination phase that follows it (same mode and
 * rules): the whole calendar is then known up to the final.
 */
class PhaseCreator
{
    /**
     * @param  array{type: string, mode?: ?string, qualifiers_per_group?: ?int, rules?: array<string, mixed>}  $data
     * @return array{phase: Phase, final: ?Phase}
     */
    public function create(Competition $competition, array $data): array
    {
        if (($data['type'] ?? null) !== PhaseType::Groups->value) {
            $data['qualifiers_per_group'] = null;
        }

        $phase = new Phase($data);
        $phase->position = (int) $competition->phases()->max('position') + 1;
        $competition->phases()->save($phase);

        if ($phase->type !== PhaseType::Groups) {
            return ['phase' => $phase, 'final' => null];
        }

        $final = new Phase([
            'type' => PhaseType::SingleElimination,
            'mode' => $phase->mode,
            'rules' => [...$phase->rules->toArray(), 'group_count' => null, 'expected_entrants' => null],
        ]);
        $final->position = $phase->position + 1;
        $competition->phases()->save($final);

        return ['phase' => $phase, 'final' => $final->setRelation('competition', $competition)];
    }

    /**
     * « Poules ajoutées, et la phase finale créée… » for the flash message.
     */
    public static function message(array $created): string
    {
        if ($created['final'] === null) {
            return 'Phase ajoutée.';
        }

        $names = PhaseCalendar::stageNames($created['final']);

        return 'Poules ajoutées, et la phase finale créée automatiquement'.($names ? ' ('.mb_strtolower(implode(', ', $names)).')' : '').' : planifiez le calendrier de chaque tour.';
    }
}
