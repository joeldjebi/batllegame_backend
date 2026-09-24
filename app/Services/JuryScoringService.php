<?php

namespace App\Services;

use App\Models\BattleMatch;
use App\Models\Judge;
use App\Models\JuryScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * Jury scores, shared by the mobile API and the web jury area.
 * A judge scores every criterion for one participant at a time (overwritable
 * while the vote is open). Authorization is the JuryScorePolicy's job.
 */
class JuryScoringService
{
    /**
     * @param  array<string, mixed>  $input  participant_id + scores[{criterion_id, score, comment?}]
     */
    public function store(Judge $judge, BattleMatch $match, array $input): void
    {
        $criteria = $match->competition->criteria()->get()->keyBy('id');

        $validated = Validator::make($input, [
            'participant_id' => ['required', 'integer', Rule::in($match->activeSlots()->pluck('participant_id'))],
            'scores' => ['required', 'array', 'size:'.$criteria->count()],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct', Rule::in($criteria->keys())],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.comment' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'scores.*.score' => 'note',
        ])->after(function (ValidationValidator $validator) use ($criteria, $input): void {
            foreach ((array) ($input['scores'] ?? []) as $i => $row) {
                $criterion = $criteria->get($row['criterion_id'] ?? null);

                if ($criterion !== null && is_numeric($row['score'] ?? null) && $row['score'] > $criterion->max_points) {
                    $validator->errors()->add("scores.{$i}.score", "La note maximale pour « {$criterion->name} » est {$criterion->max_points}.");
                }
            }
        })->validate();

        DB::transaction(function () use ($validated, $match, $judge): void {
            foreach ($validated['scores'] as $row) {
                JuryScore::query()->updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'judge_id' => $judge->id,
                        'participant_id' => $validated['participant_id'],
                        'criterion_id' => $row['criterion_id'],
                    ],
                    ['score' => $row['score'], 'comment' => $row['comment'] ?? null],
                );
            }
        });
    }
}
