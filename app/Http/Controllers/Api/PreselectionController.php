<?php

namespace App\Http\Controllers\Api;

use App\Enums\PerformanceStatus;
use App\Enums\PreselectionState;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\PreselectionSubmission;
use App\Services\PreselectionService;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pre-selection for the mobile app: public entries, likes, artist submission.
 * $entry is resolved through $competition->entries() (scoped binding).
 */
class PreselectionController extends Controller
{
    public function show(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($competition->status->isPublic(), 404);
        $preselection = $competition->preselection ?? abort(404);
        $state = $preselection->state();
        $showCounts = $state === PreselectionState::Published || $competition->settings->showLiveResults;
        $myLike = $request->user('sanctum') ? $preselection->likes()->where('user_id', $request->user('sanctum')->id)->value('submission_id') : null;

        return response()->json([
            'data' => [
                'state' => $state,
                'starts_at' => $preselection->starts_at,
                'ends_at' => $preselection->ends_at,
                'vote_ends_at' => $preselection->voteEndsAt(),
                'deliberation_ends_at' => $preselection->deliberationEndsAt(),
                'next_deadline' => $preselection->nextDeadline(),
                'likes_open' => $preselection->acceptsLikes(),
                'public_voting_enabled' => $preselection->publicVotingEnabled(),
                'selection_size' => $preselection->rules->selectionSize,
                'like_weight' => $preselection->effectiveWeights()['likes'],
                'jury_weight' => $preselection->effectiveWeights()['jury'],
                'media_rules' => [
                    'types' => $preselection->rules->mediaTypes,
                    'max_duration_seconds' => $preselection->rules->mediaMaxDuration,
                    'max_size_mb' => $preselection->rules->mediaMaxSizeMb,
                ],
                'my_like' => $myLike,
                'entries' => $preselection->entries()->where('status', PerformanceStatus::Approved)->with('participant')->get()->map(fn (PreselectionSubmission $entry) => [
                    'id' => $entry->id,
                    'stage_name' => $entry->participant->stage_name,
                    'media' => ['type' => $entry->media_type, 'url' => $entry->mediaUrl(), 'duration_seconds' => $entry->duration_seconds],
                    'likes' => $showCounts ? $entry->likes_count : null,
                    'rank' => $state === PreselectionState::Published ? $entry->rank : null,
                    'selected' => $state === PreselectionState::Published ? $entry->selected : null,
                ]),
            ],
        ]);
    }

    public function submit(Request $request, Competition $competition, PreselectionService $preselections): JsonResponse
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)->firstOr(fn () => abort(404));
        $rules = ($competition->preselection ?? abort(404))->rules;

        $request->validate([
            'media' => ['required', 'file', 'mimetypes:'.implode(',', $rules->acceptedMimeTypes()), 'max:'.($rules->mediaMaxSizeMb * 1024)],
        ]);

        $entry = $preselections->submit($participant, $request->file('media'), SubmissionService::clientModifiedAt($request));

        return response()->json(['message' => 'Prestation reçue.', 'data' => ['id' => $entry->id, 'status' => $entry->fresh()->status]], 201);
    }

    public function like(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): JsonResponse
    {
        $this->authorize('like', $entry);

        $preselections->like($request->user(), $entry, $request->header('X-Device-Id'), $request->ip());

        return response()->json(['message' => 'Like enregistré.', 'likes' => $entry->fresh()->likes_count], 201);
    }

    public function unlike(Request $request, Competition $competition, PreselectionService $preselections): JsonResponse
    {
        $preselection = $competition->preselection ?? abort(404);
        abort_unless($preselection->acceptsLikes(), 403, 'Le vote du public est clos.');

        $preselections->unlike($request->user(), $preselection);

        return response()->json(['message' => 'Like retiré.']);
    }

    public function score(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): JsonResponse
    {
        $this->authorize('score', $entry);
        $judge = $competition->judges()->where('user_id', $request->user()->id)->firstOrFail();

        $preselections->score($judge, $entry, $request->all());

        return response()->json(['message' => 'Notes enregistrées.'], 201);
    }
}
