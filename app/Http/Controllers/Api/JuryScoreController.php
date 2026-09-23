<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\JuryScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class JuryScoreController extends Controller
{
    /**
     * Store (or overwrite) the scores of the current judge for one participant.
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function store(Request $request, Competition $competition, BattleMatch $match): JsonResponse
    {
        $this->authorize('create', [JuryScore::class, $match]);

        $judge = $competition->judges()->where('user_id', $request->user()->id)->firstOrFail();
        $criteria = $competition->criteria()->get()->keyBy('id');

        $validated = Validator::make($request->all(), [
            'participant_id' => ['required', 'integer', Rule::in($match->slots()->whereNotNull('participant_id')->pluck('participant_id'))],
            'scores' => ['required', 'array', 'size:'.$criteria->count()],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct', Rule::in($criteria->keys())],
            'scores.*.score' => ['required', 'numeric', 'min:0'],
            'scores.*.comment' => ['nullable', 'string', 'max:1000'],
        ])->after(function (ValidationValidator $validator) use ($criteria, $request): void {
            foreach ((array) $request->input('scores', []) as $i => $row) {
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

        return response()->json(['message' => 'Notes enregistrées.'], 201);
    }
}
