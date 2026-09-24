<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\PerformanceStatus;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Performance;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Review of submissions and on-site captations.
 * $performance / $match are resolved through the competition (scoped binding).
 */
class PerformanceController extends Controller
{
    public function __construct(private SubmissionService $submissions) {}

    public function review(Request $request, Organizer $organizer, Competition $competition, Performance $performance): RedirectResponse|JsonResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:250'],
        ]);

        if ($performance->status === PerformanceStatus::Processing) {
            throw ValidationException::withMessages(['decision' => 'Le média est encore en cours de traitement.']);
        }

        $validated['decision'] === 'approve'
            ? $this->submissions->approve($performance, $request->user())
            : $this->submissions->reject($performance, $request->user(), $validated['reason']);

        $message = $validated['decision'] === 'approve' ? "Prestation de {$performance->participant->stage_name} validée." : "Prestation de {$performance->participant->stage_name} rejetée.";

        return $request->expectsJson() ? response()->json(['message' => $message, 'status' => $performance->fresh()->status]) : back()->with('status', $message);
    }

    /**
     * On-site: upload the recording of a participant's live performance.
     */
    public function captation(Request $request, Organizer $organizer, Competition $competition, BattleMatch $match): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'participant_id' => ['required', 'integer', Rule::in($match->slots()->whereNotNull('participant_id')->pluck('participant_id'))],
            'media' => ['required', 'file', 'mimetypes:'.implode(',', $match->phase->rules->acceptedMimeTypes()), 'max:'.($match->phase->rules->mediaMaxSizeMb * 1024)],
        ]);

        $participant = $competition->participants()->findOrFail($validated['participant_id']);
        $this->submissions->captation($match, $participant, $request->file('media'), $request->user());

        return back()->with('status', "Captation de {$participant->stage_name} ajoutée.");
    }
}
