<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Artist portal: my participations, the stage in progress and my submission,
 * plus the competitions open for registration.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $participations = $user->participations()
            ->whereHas('competition')
            ->with('competition.organizer')
            ->latest()
            ->get()
            ->map(function ($participant) {
                $stage = $participant->currentStage();

                return [
                    'participant' => $participant,
                    'stage' => $stage,
                    'submission' => $stage?->performances()->where('participant_id', $participant->id)->first(),
                    'playing' => $stage && in_array($participant->id, $stage->participantIds(), true),
                ];
            });

        $open = Competition::query()
            ->where('status', CompetitionStatus::Registration)
            ->where(fn ($q) => $q->whereNull('registration_ends_at')->orWhere('registration_ends_at', '>', now()))
            ->whereDoesntHave('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereDoesntHave('judges', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('organizer', fn ($q) => $q->where('status', '!=', 'suspendu'))
            ->with('organizer')
            ->withCount('participants')
            ->latest()
            ->get();

        return view('portal.artist.dashboard', ['participations' => $participations, 'open' => $open]);
    }
}
