<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * $participant is resolved through $competition->participants() (scoped binding).
 */
class ParticipantController extends Controller
{
    public function update(Request $request, Organizer $organizer, Competition $competition, Participant $participant): RedirectResponse
    {
        $this->authorize('manageRegistrations', $competition);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ParticipantStatus::class)->only([
                ParticipantStatus::Validated,
                ParticipantStatus::Withdrawn,
                ParticipantStatus::Disqualified,
            ])],
            'seed' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1024'],
        ]);

        $participant->update($validated);

        return back()->with('status', 'Participant mis à jour.');
    }
}
