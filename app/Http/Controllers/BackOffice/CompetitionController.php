<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\CompetitionRequest;
use App\Models\Competition;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * $competition is resolved through $organizer->competitions() (scoped binding),
 * so a competition of another organizer is a 404 before any policy runs.
 */
class CompetitionController extends Controller
{
    public function store(CompetitionRequest $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('create', [Competition::class, $organizer]);

        $competition = new Competition($request->validated());
        $competition->slug = Competition::uniqueSlug($competition->name);
        $competition->status = CompetitionStatus::Draft;
        $competition->organizer()->associate($organizer);
        $competition->creator()->associate($request->user());
        $competition->save();

        return redirect()->route('organizers.competitions.show', [$organizer, $competition])
            ->with('status', 'Compétition créée en brouillon.');
    }

    public function show(Organizer $organizer, Competition $competition): View
    {
        $this->authorize('view', $competition);

        return view('competitions.show', [
            'organizer' => $organizer,
            'competition' => $competition->load([
                'phases.groups.standings.participant',
                'phases.matches' => fn ($q) => $q->orderBy('group_id')->orderBy('bracket')->orderBy('round')->orderBy('bracket_position'),
                'phases.matches.slots.participant',
                'phases.matches.group',
                'phases.stages.performances.participant',
                'participants.user',
                'judges.user',
                'criteria',
            ]),
        ]);
    }

    public function update(CompetitionRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $competition->update($request->safe()->except('status'));

        return back()->with('status', 'Compétition mise à jour.');
    }

    public function updateStatus(Request $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(CompetitionStatus::class)]]);
        $status = CompetitionStatus::from($validated['status']);

        $this->authorize('changeStatus', [$competition, $status]);

        $competition->update(['status' => $status]);

        return back()->with('status', "Statut : {$status->label()}.");
    }

    public function destroy(Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('delete', $competition);

        $competition->delete();

        return redirect()->route('organizers.show', $organizer)->with('status', 'Compétition supprimée.');
    }
}
