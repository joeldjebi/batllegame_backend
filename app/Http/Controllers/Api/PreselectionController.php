<?php

namespace App\Http\Controllers\Api;

use App\Enums\PerformanceStatus;
use App\Enums\PreselectionState;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Fan\PreselectionController as FanPreselectionController;
use App\Models\Competition;
use App\Models\PreselectionSubmission;
use App\Services\JuryWorkload;
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
    /**
     * State, dates, rules and weights; the entries are paged by entries().
     */
    public function show(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($competition->status->isPublic(), 404);
        $preselection = $competition->preselection ?? abort(404);
        $viewer = $request->user('sanctum');

        return response()->json([
            'data' => [
                'state' => $preselection->state(),
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
                'entries_count' => $preselection->entries()->where('status', PerformanceStatus::Approved)->count(),
                'my_like' => $viewer ? $preselection->likes()->where('user_id', $viewer->id)->value('submission_id') : null,
            ],
        ]);
    }

    /**
     * Public entries, cursor-paginated: newest first, by rank once the selection is published.
     */
    public function entries(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($competition->status->isPublic(), 404);
        $preselection = $competition->preselection ?? abort(404);
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50'], 'q' => ['nullable', 'string', 'max:80']]);

        $published = $preselection->state() === PreselectionState::Published;
        $viewerId = $request->user('sanctum')?->id;
        $myLike = $viewerId ? $preselection->likes()->where('user_id', $viewerId)->value('submission_id') : null;
        // Counts: after the viewer's own like, with live results, or once published.
        $showCounts = FanPreselectionController::showsAllCounts($preselection, $myLike);
        $search = trim((string) ($validated['q'] ?? ''));

        $page = $preselection->entries()
            ->where('status', PerformanceStatus::Approved)
            ->when($published, fn ($q) => $q->whereNotNull('rank')->orderBy('rank')->orderBy('id'), fn ($q) => $q->orderByDesc('id'))
            ->when($search !== '', fn ($q) => $q->whereHas('participant', fn ($p) => $p->whereLike('stage_name', "%{$search}%")))
            ->with('participant.user')
            ->cursorPaginate((int) ($validated['limit'] ?? 20))
            ->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (PreselectionSubmission $entry) => [
                'id' => $entry->id,
                'participant_id' => $entry->participant_id,
                'stage_name' => $entry->participant->stage_name,
                'avatar_url' => $entry->participant->user?->avatarUrl(),
                'media' => ['type' => $entry->media_type, 'url' => $entry->mediaUrl(), 'poster_url' => $entry->posterUrl(), 'width' => $entry->width, 'height' => $entry->height, 'duration_seconds' => $entry->duration_seconds],
                // An artist always sees the count of their own entry.
                'likes' => $showCounts || ($viewerId && $entry->participant->user_id === $viewerId) ? $entry->likes_count : null,
                'liked' => $myLike === $entry->id,
                'rank' => $published ? $entry->rank : null,
                'selected' => $published ? $entry->selected : null,
                'share_url' => route('fan.competitions.preselection.entry', [$competition, $entry]),
            ])->all(),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'my_like' => $myLike],
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

        return response()->json([
            'message' => 'Like enregistré.',
            'likes' => $entry->fresh()->likes_count,
            ...FanPreselectionController::likesState($competition->preselection, $request->user()),
        ], 201);
    }

    public function unlike(Request $request, Competition $competition, PreselectionService $preselections): JsonResponse
    {
        $preselection = $competition->preselection ?? abort(404);
        abort_unless($preselection->acceptsLikes(), 403, 'Le vote du public est clos.');

        $preselections->unlike($request->user(), $preselection);

        return response()->json(['message' => 'Like retiré.', ...FanPreselectionController::likesState($preselection->fresh(), $request->user())]);
    }

    public function score(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): JsonResponse
    {
        $this->authorize('score', $entry);
        $judge = $competition->judges()->where('user_id', $request->user()->id)->firstOrFail();

        $preselections->score($judge, $entry, $request->all());

        return response()->json([
            'message' => 'Notes enregistrées.',
            'next_entry_id' => app(JuryWorkload::class)->nextToScore($judge, $competition->preselection, $entry->id),
        ], 201);
    }
}
