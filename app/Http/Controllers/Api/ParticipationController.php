<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StageResource;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Participant area: my competitions, the stage in progress and my submission.
 */
class ParticipationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $participations = $request->user()->participations()
            ->whereHas('competition')
            ->with('competition.organizer')
            ->latest()
            ->get();

        return response()->json([
            'data' => $participations->map(function (Participant $participant) {
                $stage = $participant->currentStage();
                $submission = $stage?->performances()->where('participant_id', $participant->id)->first();

                return [
                    'id' => $participant->id,
                    'stage_name' => $participant->stage_name,
                    'status' => $participant->status,
                    'competition' => [
                        'id' => $participant->competition->id,
                        'slug' => $participant->competition->slug,
                        'name' => $participant->competition->name,
                        'status' => $participant->competition->status,
                        'organizer' => $participant->competition->organizer->name,
                    ],
                    'current_stage' => $stage ? new StageResource($stage) : null,
                    'submission' => $submission ? [
                        'status' => $submission->status,
                        'rejection_reason' => $submission->rejection_reason,
                        'url' => $submission->mediaUrl(),
                        'submitted_at' => $submission->updated_at,
                    ] : null,
                ];
            }),
        ]);
    }
}
