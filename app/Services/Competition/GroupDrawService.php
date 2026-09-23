<?php

namespace App\Services\Competition;

use App\Enums\GroupDrawMethod;
use App\Models\BattleMatch;
use App\Models\Group;
use App\Models\Participant;
use App\Models\Phase;
use Illuminate\Support\Collection;

/**
 * Creates the groups of a phase, distributes the entrants and schedules the
 * round-robin matches of each group.
 */
class GroupDrawService
{
    /**
     * @param  Collection<int, Participant>  $entrants  Ordered by seed (best first).
     * @return Collection<int, Group>
     */
    public function draw(Phase $phase, Collection $entrants): Collection
    {
        $rules = $phase->rules;
        $groupCount = $rules->groupCount;

        $ordered = $rules->drawMethod === GroupDrawMethod::Random
            ? $entrants->shuffle()->values()
            : $entrants->values();

        $groups = collect(range(0, $groupCount - 1))
            ->map(fn (int $i) => $phase->groups()->create(['name' => 'Poule '.self::letter($i)]));

        // Snake distribution (A B C C B A ...) spreads the best seeds across groups.
        $members = array_fill(0, $groupCount, []);

        foreach ($ordered as $index => $participant) {
            $lap = intdiv($index, $groupCount);
            $offset = $index % $groupCount;
            $members[$lap % 2 === 0 ? $offset : $groupCount - 1 - $offset][] = $participant;
        }

        foreach ($groups as $i => $group) {
            $group->participants()->attach(array_map(fn (Participant $p) => $p->id, $members[$i]));
            $this->scheduleMatches($phase, $group, $members[$i]);
        }

        return $groups;
    }

    /**
     * @param  list<Participant>  $members
     */
    private function scheduleMatches(Phase $phase, Group $group, array $members): void
    {
        foreach (RoundRobinScheduler::rounds($members) as $roundIndex => $pairs) {
            foreach ($pairs as $position => [$home, $away]) {
                $match = new BattleMatch([
                    'phase_id' => $phase->id,
                    'group_id' => $group->id,
                    'round' => $roundIndex + 1,
                    'bracket_position' => $position + 1,
                ]);
                $match->save();

                $match->slots()->createMany([
                    ['slot' => 1, 'participant_id' => $home->id],
                    ['slot' => 2, 'participant_id' => $away->id],
                ]);
            }
        }
    }

    /**
     * 0 => A, 25 => Z, 26 => AA.
     */
    public static function letter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + $i % 26).$letter;
        }

        return $letter;
    }
}
