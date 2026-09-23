<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\CreateJudgeRequest;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Organizer;
use App\Services\JudgeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * $judge is resolved through $competition->judges() (scoped binding).
 */
class JudgeController extends Controller
{
    public function store(CreateJudgeRequest $request, Organizer $organizer, Competition $competition, JudgeAccountService $accounts): RedirectResponse
    {
        $this->authorize('update', $competition);

        [$judge, $password] = $accounts->assign($competition, $request->country(), (string) $request->input('phone'), $request->validated('name'));

        return back()->with('status', $password
            ? "Compte juré créé pour {$judge->user->name}. Mot de passe provisoire : {$password} (envoyé par SMS, à changer à la première connexion sur /jury)."
            : "{$judge->user->name} a été ajouté au jury et prévenu par SMS.");
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
