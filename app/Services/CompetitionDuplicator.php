<?php

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copy a competition as a new draft of the same organizer: presentation, rewards,
 * settings, scoring criteria and phase structure. Participants, judges, matches,
 * votes, payments and the pre-selection (dated) are never copied.
 */
class CompetitionDuplicator
{
    public function duplicate(Competition $source, User $by): Competition
    {
        return DB::transaction(function () use ($source, $by): Competition {
            $name = str($source->name)->limit(240, '')->append(' (copie)')->toString();

            $copy = new Competition([
                'name' => $name,
                'slug' => Competition::uniqueSlug($name),
                'discipline' => $source->discipline,
                'mode' => $source->mode,
                'max_participants' => $source->max_participants,
                'entry_fee' => $source->entry_fee,
                'currency' => $source->currency,
                'settings' => $source->settings,
                'prizes' => $source->prizes,
            ]);
            // Already sanitized: bypass the mutator's work but keep the same value.
            $copy->description = $source->description;
            $copy->status = CompetitionStatus::Draft;
            $copy->organizer()->associate($source->organizer_id);
            $copy->creator()->associate($by);
            $copy->save();

            foreach ($source->criteria()->orderBy('position')->get() as $criterion) {
                $copy->criteria()->create($criterion->only(['name', 'max_points', 'weight', 'position']));
            }

            foreach ($source->phases()->orderBy('position')->get() as $phase) {
                $copy->phases()->create([
                    'type' => $phase->type,
                    'position' => $phase->position,
                    'mode' => $phase->mode,
                    'qualifiers_per_group' => $phase->qualifiers_per_group,
                    'rules' => $phase->rules,
                ]);
            }

            return $copy;
        });
    }
}
