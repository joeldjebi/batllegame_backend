<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Stage;
use App\Services\RegistrationService;
use App\Services\SubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParticipationController extends Controller
{
    public function register(Request $request, Competition $competition, RegistrationService $registrations): RedirectResponse
    {
        $this->authorize('register', [Participant::class, $competition]);

        $validated = $request->validate(['stage_name' => ['required', 'string', 'max:100']]);
        $registrations->register($request->user(), $competition, $validated['stage_name']);

        if ($competition->requiresPayment()) {
            return redirect()->route('artist.competitions.payment', $competition)
                ->with('status', 'Inscription enregistrée : réglez les frais pour la confirmer.');
        }

        return back()->with('status', "Inscription à « {$competition->name} » enregistrée.");
    }

    /**
     * $stage is resolved through $competition->stages() (scoped binding).
     */
    public function submit(Request $request, Competition $competition, Stage $stage, SubmissionService $submissions): RedirectResponse
    {
        $participant = $stage->participantFor($request->user()) ?? abort(403, 'Vous ne participez pas à cette étape.');

        if ($competition->organizer->isSuspended() || ! $stage->acceptsSubmissions()) {
            throw CompetitionFlowException::submissionsClosed();
        }

        $rules = $stage->phase->rules;
        $request->validate([
            'media' => ['required', 'file', 'mimetypes:'.implode(',', $rules->acceptedMimeTypes()), 'max:'.($rules->mediaMaxSizeMb * 1024)],
        ], [
            'media.mimetypes' => 'Format non accepté pour cette étape.',
            'media.max' => "Le fichier dépasse la taille maximale de {$rules->mediaMaxSizeMb} Mo.",
        ]);

        $submissions->submit($participant, $stage, $request->file('media'));

        return back()->with('status', 'Votre prestation a bien été envoyée.');
    }
}
