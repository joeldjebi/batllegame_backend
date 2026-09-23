<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function store(Request $request, Competition $competition, RegistrationService $registrations): JsonResponse
    {
        $this->authorize('register', [Participant::class, $competition]);

        $validated = $request->validate(['stage_name' => ['required', 'string', 'max:100']]);

        $participant = $registrations->register($request->user(), $competition, $validated['stage_name']);

        return response()->json([
            'id' => $participant->id,
            'stage_name' => $participant->stage_name,
            'status' => $participant->status,
        ], 201);
    }
}
