<?php

namespace App\Http\Controllers\Api;

use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function store(Request $request, Competition $competition): JsonResponse
    {
        $this->authorize('register', [Participant::class, $competition]);

        $validated = $request->validate([
            'stage_name' => ['required', 'string', 'max:100'],
        ]);

        $participant = new Participant([
            'stage_name' => $validated['stage_name'],
            'status' => $competition->settings->registrationRequiresApproval
                ? ParticipantStatus::Registered
                : ParticipantStatus::Validated,
        ]);
        $participant->user()->associate($request->user());

        try {
            // Savepoint: a unique violation (concurrent double registration) must not abort an outer transaction.
            DB::transaction(fn () => $competition->participants()->save($participant));
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Vous êtes déjà inscrit à cette compétition.');
        }

        return response()->json([
            'id' => $participant->id,
            'stage_name' => $participant->stage_name,
            'status' => $participant->status,
        ], 201);
    }
}
