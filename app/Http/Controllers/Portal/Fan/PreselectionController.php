<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\PreselectionSubmission;
use App\Services\PreselectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Public likes during the pre-selection (one per user and competition).
 * $entry is resolved through $competition->entries() (scoped binding).
 */
class PreselectionController extends Controller
{
    public function like(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): RedirectResponse
    {
        $this->authorize('like', $entry);

        try {
            $preselections->like($request->user(), $entry, 'web-'.substr(hash('sha256', (string) $request->userAgent()), 0, 32), $request->ip());
        } catch (HttpExceptionInterface $e) {
            return back()->withErrors(['like' => $e->getMessage()]);
        }

        return back()->with('status', "Vous soutenez {$entry->participant->stage_name} !");
    }

    public function unlike(Request $request, Competition $competition, PreselectionService $preselections): RedirectResponse
    {
        $preselection = $competition->preselection ?? abort(404);
        abort_unless($preselection->acceptsLikes(), 403, 'Le vote du public est clos.');

        $preselections->unlike($request->user(), $preselection);

        return back()->with('status', 'Like retiré.');
    }
}
