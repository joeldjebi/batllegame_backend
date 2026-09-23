<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\PerformanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\PreselectionRequest;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\PreselectionSubmission;
use App\Services\PreselectionService;
use App\Services\SubmissionService;
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

        $preselection = $this->preselections->configure($competition, $request->validated());

        return back()->with('status', $preselection->wasRecentlyCreated
            ? 'Présélection créée : les artistes inscrits pourront soumettre leur prestation pendant la période.'
            : 'Présélection mise à jour.');
    }

    public function review(Request $request, Organizer $organizer, Competition $competition, PreselectionSubmission $entry, SubmissionService $submissions): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:250'],
        ]);

        if ($entry->status === PerformanceStatus::Processing) {
            throw ValidationException::withMessages(['decision' => 'Le média est encore en cours de traitement.']);
        }

        $validated['decision'] === 'approve'
            ? $submissions->approve($entry, $request->user())
            : $submissions->reject($entry, $request->user(), $validated['reason']);

        return back()->with('status', $validated['decision'] === 'approve' ? 'Prestation validée.' : 'Prestation rejetée.');
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
