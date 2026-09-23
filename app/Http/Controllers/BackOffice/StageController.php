<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Stage;
use App\Services\Competition\StageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * $stage is resolved through $competition->stages() (scoped binding).
 */
class StageController extends Controller
{
    public function __construct(private StageService $stages) {}

    public function update(Request $request, Organizer $organizer, Competition $competition, Stage $stage): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'submission_deadline' => ['nullable', 'date'],
            'voting_opens_at' => ['nullable', 'date', 'after_or_equal:submission_deadline'],
            'voting_closes_at' => ['nullable', 'date', 'after:voting_opens_at', 'after:submission_deadline', 'after:now'],
        ]);

        $this->stages->schedule($stage, $validated);

        return back()->with('status', "Calendrier de « {$stage->name} » enregistré.");
    }

    public function openSubmissions(Organizer $organizer, Competition $competition, Stage $stage): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $this->stages->openSubmissions($stage);

        return back()->with('status', "Soumissions ouvertes pour « {$stage->name} ».");
    }

    public function openVoting(Organizer $organizer, Competition $competition, Stage $stage): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $this->stages->openVoting($stage);

        return back()->with('status', "Vote ouvert pour « {$stage->name} ».");
    }
}
