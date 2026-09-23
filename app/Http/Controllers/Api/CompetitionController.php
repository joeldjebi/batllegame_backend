<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\MatchResource;
use App\Models\BattleMatch;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Public read access for the mobile app. Drafts are never exposed.
 */
class CompetitionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_filter(CompetitionStatus::values(), fn ($s) => $s !== CompetitionStatus::Draft->value))],
            'discipline' => ['nullable', 'string'],
        ]);

        $competitions = Competition::query()
            ->with('organizer')
            ->where('status', '!=', CompetitionStatus::Draft)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('discipline'), fn ($q, $discipline) => $q->where('discipline', $discipline))
            ->latest()
            ->paginate(20);

        return CompetitionResource::collection($competitions);
    }

    public function show(Competition $competition): CompetitionResource
    {
        abort_unless($competition->status->isPublic(), 404);

        return new CompetitionResource($competition->load(['organizer', 'phases', 'criteria']));
    }

    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function showMatch(Competition $competition, BattleMatch $match): MatchResource
    {
        abort_unless($competition->status->isPublic(), 404);

        return new MatchResource($match->load(['phase', 'slots.participant', 'competition']));
    }
}
