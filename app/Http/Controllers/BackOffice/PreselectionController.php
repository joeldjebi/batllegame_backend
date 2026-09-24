<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\PerformanceStatus;
use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\PreselectionRequest;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Organizer;
use App\Models\PreselectionSubmission;
use App\Services\JuryWorkload;
use App\Services\PreselectionService;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pre-selection of a competition: configuration, review of the entries and
 * publication of the selection. $entry is resolved through $competition->entries().
 */
class PreselectionController extends Controller
{
    public function __construct(private PreselectionService $preselections) {}

    public function update(PreselectionRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $preselection = $this->preselections->configure($competition, $request->preselectionData());
        app(JuryWorkload::class)->sync($preselection);

        return back()->with('status', $preselection->wasRecentlyCreated
            ? 'Présélection créée : les artistes inscrits pourront soumettre leur prestation pendant la période.'
            : 'Présélection mise à jour.');
    }

    public function review(Request $request, Organizer $organizer, Competition $competition, PreselectionSubmission $entry, SubmissionService $submissions): RedirectResponse|JsonResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:250'],
        ]);

        if ($entry->status === PerformanceStatus::Processing) {
            throw ValidationException::withMessages(['decision' => 'Le média est encore en cours de traitement.']);
        }

        // Only paid artists compete (their entry may predate a refund or a payment fix).
        if ($validated['decision'] === 'approve' && ! $entry->participant->hasPaid()) {
            throw CompetitionFlowException::entryUnpaid();
        }

        $validated['decision'] === 'approve'
            ? $submissions->approve($entry, $request->user())
            : $submissions->reject($entry, $request->user(), $validated['reason']);

        $message = $validated['decision'] === 'approve'
            ? "Prestation de {$entry->participant->stage_name} validée : visible du public et du jury."
            : "Prestation de {$entry->participant->stage_name} rejetée : l'artiste peut en envoyer une autre.";

        return $request->expectsJson() ? response()->json(['message' => $message, 'status' => $entry->fresh()->status]) : back()->with('status', $message);
    }

    /**
     * Erase a judge's (final) notes on an entry after a mistake: they score it again.
     */
    public function reopenScore(Request $request, Organizer $organizer, Competition $competition, PreselectionSubmission $entry, Judge $judge): RedirectResponse|JsonResponse
    {
        $this->authorize('runMatches', $competition);
        // Bound without scoping: both must belong to this competition.
        abort_unless($judge->competition_id === $competition->id && $entry->competition_id === $competition->id && $competition->organizer_id === $organizer->id, 404);

        $this->preselections->reopenScore($entry, $judge);
        $message = "Note de {$judge->user->name} rouverte pour {$entry->participant->stage_name} : il peut la refaire.";

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    public function rank(Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $this->preselections->rank($competition->preselection ?? abort(404));

        return back()->with('status', 'Classement recalculé.');
    }

    public function publish(Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $selected = $this->preselections->publish($competition->preselection ?? abort(404));

        return back()->with('status', "Sélection publiée : {$selected} artiste(s) retenu(s) pour la compétition.");
    }
}
