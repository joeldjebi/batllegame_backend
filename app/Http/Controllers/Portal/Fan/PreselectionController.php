<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Enums\PerformanceStatus;
use App\Enums\PreselectionState;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Preselection;
use App\Models\PreselectionSubmission;
use App\Models\User;
use App\Services\PreselectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Public likes during the pre-selection (one per user and competition), used in AJAX
 * by the fan page (JSON) with a classic form fallback (redirect).
 * $entry is resolved through $competition->entries() (scoped binding).
 */
class PreselectionController extends Controller
{
    /**
     * Shareable page of one published entry: the link artists and fans send to invite people to like.
     */
    public function show(Request $request, Competition $competition, PreselectionSubmission $entry): View
    {
        abort_unless($competition->status->isPublic() && $entry->status === PerformanceStatus::Approved, 404);

        $preselection = $entry->preselection;
        $user = $request->user('member');

        return view('portal.fan.entry', [
            'competition' => $competition->load('organizer'),
            'preselection' => $preselection,
            'entry' => $entry->load('participant.user'),
            'likes' => self::likesState($preselection, $user),
            'user' => $user,
            'others' => $preselection->entries()->where('status', PerformanceStatus::Approved)->whereKeyNot($entry->id)->count(),
        ]);
    }

    public function like(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): JsonResponse|RedirectResponse
    {
        $this->authorize('like', $entry);

        try {
            $preselections->like($request->user(), $entry, 'web-'.substr(hash('sha256', (string) $request->userAgent()), 0, 32), $request->ip());
        } catch (HttpExceptionInterface $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], $e->getStatusCode())
                : back()->withErrors(['like' => $e->getMessage()]);
        }

        return $this->respond($request, $entry->preselection, "Vous soutenez {$entry->participant->stage_name} !");
    }

    public function unlike(Request $request, Competition $competition, PreselectionService $preselections): JsonResponse|RedirectResponse
    {
        $preselection = $competition->preselection ?? abort(404);
        abort_unless($preselection->acceptsLikes(), 403, 'Le vote du public est clos.');

        $preselections->unlike($request->user(), $preselection);

        return $this->respond($request, $preselection, 'Like retiré.');
    }

    /**
     * State of the likes shown on the fan page: the viewer's like and the count of every
     * published entry, visible once the viewer has liked (like a poll), when the organizer
     * shows live results, or after publication. An artist always sees the count of their own entry
     * (they cannot like it). `counts` only holds the entries whose count is visible.
     *
     * @return array{my_like: ?int, counts: ?array<int, int>, can_like: bool}
     */
    public static function likesState(Preselection $preselection, ?User $user): array
    {
        $myLike = $user ? $preselection->likes()->where('user_id', $user->id)->value('submission_id') : null;
        $entries = $preselection->entries()->where('status', PerformanceStatus::Approved);

        $counts = match (true) {
            self::showsAllCounts($preselection, $myLike) => $entries->pluck('likes_count', 'id')->all(),
            $user !== null => $entries->whereHas('participant', fn ($q) => $q->where('user_id', $user->id))->pluck('likes_count', 'id')->all() ?: null,
            default => null,
        };

        return [
            'my_like' => $myLike,
            'counts' => $counts,
            'can_like' => $preselection->acceptsLikes(),
        ];
    }

    public static function showsAllCounts(Preselection $preselection, ?int $myLike): bool
    {
        return $myLike !== null || $preselection->state() === PreselectionState::Published || $preselection->competition->settings->showLiveResults;
    }

    private function respond(Request $request, Preselection $preselection, string $message): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        return response()->json(['message' => $message, ...self::likesState($preselection->fresh(), $request->user())]);
    }
}
