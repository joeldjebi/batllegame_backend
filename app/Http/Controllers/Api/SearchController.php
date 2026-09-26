<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\Participant;
use App\Services\Feed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Search of the mobile app: competitions (name, organizer) and artists (stage name),
 * the videos coming from /feed?q=. Empty query: suggestions (running competitions,
 * the artists of the latest videos).
 */
class SearchController extends Controller
{
    private const int COMPETITIONS = 8;

    private const int ARTISTS = 20;

    public function __invoke(Request $request, Feed $feed): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $search = Feed::escapeLike((string) ($validated['q'] ?? ''));

        $competitions = Competition::query()
            ->with('organizer')
            ->where('status', '!=', CompetitionStatus::Draft)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', "%{$search}%")
                ->orWhereHas('organizer', fn ($o) => $o->whereLike('name', "%{$search}%"))))
            ->when($search === '', fn ($q) => $q->whereIn('status', [CompetitionStatus::InProgress, CompetitionStatus::Registration]))
            ->latest()
            ->limit(self::COMPETITIONS)
            ->get();

        $artists = $search !== '' ? $this->artists($search) : $this->recentArtists($feed);

        return response()->json([
            'competitions' => CompetitionResource::collection($competitions)->resolve(),
            'artists' => $artists->values(),
        ]);
    }

    /**
     * Artists by stage name, one row per person (their latest participation).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function artists(string $search): Collection
    {
        return Participant::query()
            ->with(['user', 'competition'])
            ->whereHas('competition', fn ($q) => $q->where('status', '!=', CompetitionStatus::Draft))
            ->whereLike('stage_name', "%{$search}%")
            ->latest('id')
            ->limit(self::ARTISTS * 3)
            ->get()
            ->unique('user_id')
            ->take(self::ARTISTS)
            ->map(fn (Participant $participant) => self::artist($participant));
    }

    /**
     * Suggestions: the artists of the latest public videos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function recentArtists(Feed $feed): Collection
    {
        $ids = collect($feed->page(null, null, Feed::MAX_LIMIT)['items'])->pluck('artist.participant_id')->filter()->unique();

        return Participant::query()->with(['user', 'competition'])->whereIn('id', $ids)->get()
            ->sortBy(fn (Participant $p) => $ids->search($p->id))
            ->unique('user_id')
            ->take(10)
            ->map(fn (Participant $participant) => self::artist($participant));
    }

    /**
     * @return array<string, mixed>
     */
    private static function artist(Participant $participant): array
    {
        return [
            'participant_id' => $participant->id,
            'stage_name' => $participant->stage_name,
            'avatar_url' => $participant->user?->avatarUrl(),
            'competition' => ['slug' => $participant->competition->slug, 'name' => $participant->competition->name],
        ];
    }
}
