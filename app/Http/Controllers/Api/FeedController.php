<?php

namespace App\Http\Controllers\Api;

use App\Enums\Discipline;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\Feed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * « Pour toi »: the vertical video feed of the mobile app (public, the viewer's
 * like state when signed in).
 */
class FeedController extends Controller
{
    public function __invoke(Request $request, Feed $feed): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:300'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.Feed::MAX_LIMIT],
            'competition' => ['nullable', 'string', 'max:120'],
            'discipline' => ['nullable', Rule::enum(Discipline::class)],
        ]);

        $competitionId = null;
        if (filled($validated['competition'] ?? null)) {
            $competition = Competition::query()->where('slug', $validated['competition'])->first();
            abort_unless($competition?->status->isPublic(), 404);
            $competitionId = $competition->id;
        }

        $page = $feed->page($request->user('sanctum'), $validated['cursor'] ?? null, (int) ($validated['limit'] ?? Feed::DEFAULT_LIMIT), [
            'competition_id' => $competitionId,
            'discipline' => $validated['discipline'] ?? null,
        ]);

        return response()->json(['data' => $page['items'], 'meta' => ['next_cursor' => $page['next_cursor']]]);
    }
}
