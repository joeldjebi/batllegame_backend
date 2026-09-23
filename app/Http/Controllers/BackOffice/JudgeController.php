<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\JudgeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\InviteUserRequest;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * $judge is resolved through $competition->judges() (scoped binding).
 */
class JudgeController extends Controller
{
    public function store(InviteUserRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $user = $request->invitedUser();

        if ($user->isParticipantOf($competition)) {
            throw ValidationException::withMessages(['phone' => 'Un participant ne peut pas être membre du jury de la même compétition.']);
        }

        if ($user->isJudgeOf($competition, acceptedOnly: false)) {
            throw ValidationException::withMessages(['phone' => 'Cet utilisateur fait déjà partie du jury.']);
        }

        $competition->judges()->create(['user_id' => $user->id, 'status' => JudgeStatus::Invited]);

        return back()->with('status', 'Juré invité.');
    }

    public function destroy(Organizer $organizer, Competition $competition, Judge $judge): RedirectResponse
    {
        $this->authorize('update', $competition);

        if ($judge->scores()->exists()) {
            throw ValidationException::withMessages(['judge' => 'Ce juré a déjà noté des matchs.']);
        }

        $judge->delete();

        return back()->with('status', 'Juré retiré.');
    }
}
