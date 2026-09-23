<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Stage;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A participant submits their media (video or audio) for a stage.
 * $stage is resolved through $competition->stages() (scoped binding).
 */
class SubmissionController extends Controller
{
    public function show(Request $request, Competition $competition, Stage $stage): JsonResponse
    {
        $participant = $this->participantOf($request, $competition, $stage);
        $submission = $stage->performances()->where('participant_id', $participant->id)->first();

        return response()->json([
            'data' => $submission ? [
                'status' => $submission->status,
                'type' => $submission->media_type,
                'url' => $submission->mediaUrl(),
                'duration_seconds' => $submission->duration_seconds,
                'rejection_reason' => $submission->rejection_reason,
                'submitted_at' => $submission->updated_at,
            ] : null,
        ]);
    }

    public function store(Request $request, Competition $competition, Stage $stage, SubmissionService $submissions): JsonResponse
    {
        $participant = $this->participantOf($request, $competition, $stage);

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

        $performance = $submissions->submit($participant, $stage, $request->file('media'), SubmissionService::clientModifiedAt($request));

        return response()->json([
            'message' => 'Soumission reçue.',
            'data' => ['status' => $performance->fresh()->status, 'type' => $performance->media_type],
        ], 201);
    }

    /**
     * Only a participant playing a match of this stage may submit.
     */
    private function participantOf(Request $request, Competition $competition, Stage $stage): Participant
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)->first();

        abort_if($participant === null || ! in_array($participant->id, $stage->participantIds(), true), 403, 'Vous ne participez pas à cette étape.');

        return $participant;
    }
}
