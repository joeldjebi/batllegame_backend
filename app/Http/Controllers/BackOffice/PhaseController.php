<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\PhaseRequest;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Phase;
use App\Services\Competition\PhaseLauncher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * $phase is resolved through $competition->phases() (scoped binding).
 */
class PhaseController extends Controller
{
    public function store(PhaseRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $phase = new Phase($request->validated());
        $phase->position = (int) $competition->phases()->max('position') + 1;
        $competition->phases()->save($phase);

        return back()->with('status', 'Phase ajoutée.');
    }

    public function update(PhaseRequest $request, Organizer $organizer, Competition $competition, Phase $phase): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureNotStarted($phase);

        $phase->update($request->validated());

        return back()->with('status', 'Phase mise à jour.');
    }

    public function destroy(Organizer $organizer, Competition $competition, Phase $phase): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureNotStarted($phase);

        $phase->delete();

        return back()->with('status', 'Phase supprimée.');
    }

    /**
     * Freeze the rules and generate the groups or the bracket.
     */
    public function start(Organizer $organizer, Competition $competition, Phase $phase, PhaseLauncher $launcher): RedirectResponse
    {
        $this->authorize('update', $competition);

        $launcher->start($phase);

        return back()->with('status', 'Phase démarrée : les matchs ont été générés.');
    }

    private function ensureNotStarted(Phase $phase): void
    {
        if ($phase->isFrozen()) {
            throw ValidationException::withMessages(['phase' => 'Cette phase a démarré : ses règles sont figées.']);
        }
    }
}
