<?php

namespace App\Http\Controllers\Api;

use App\Enums\StageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StageResource;
use App\Models\Participant;
use App\Models\Stage;
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
                $stage = $this->currentStage($participant);
                $submission = $stage?->performances()->where('participant_id', $participant->id)->first();

                return [
                    'id' => $participant->id,
                    'stage_name' => $participant->stage_name,
                    'status' => $participant->status,
                    'competition' => [
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

    /**
     * The latest stage (not closed) where this participant has a match to play.
     */
    private function currentStage(Participant $participant): ?Stage
    {
        return Stage::query()
            ->where('status', '!=', StageStatus::Closed)
            ->whereHas('matches.slots', fn ($q) => $q->where('participant_id', $participant->id))
            ->whereHas('phase', fn ($q) => $q->where('competition_id', $participant->competition_id))
            ->with('phase.competition')
            ->orderBy('phase_id')->orderBy('number')
            ->first();
    }
}
