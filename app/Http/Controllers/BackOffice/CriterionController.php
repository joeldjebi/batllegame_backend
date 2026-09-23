<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\CriterionRequest;
use App\Models\Competition;
use App\Models\Criterion;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * $criterion is resolved through $competition->criteria() (scoped binding).
 */
class CriterionController extends Controller
{
    public function store(CriterionRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $competition->criteria()->create($request->validated());

        return back()->with('status', 'Critère ajouté.');
    }

    public function update(CriterionRequest $request, Organizer $organizer, Competition $competition, Criterion $criterion): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureUnused($criterion);

        $criterion->update($request->validated());

        return back()->with('status', 'Critère mis à jour.');
    }

    public function destroy(Organizer $organizer, Competition $competition, Criterion $criterion): RedirectResponse
    {
        $this->authorize('update', $competition);
        $this->ensureUnused($criterion);

        $criterion->delete();

        return back()->with('status', 'Critère supprimé.');
    }

    /**
     * Changing a criterion already used would silently alter past scores.
     */
    private function ensureUnused(Criterion $criterion): void
    {
        if ($criterion->scores()->exists()) {
            throw ValidationException::withMessages(['criterion' => 'Ce critère a déjà été utilisé pour noter.']);
        }
    }
}
