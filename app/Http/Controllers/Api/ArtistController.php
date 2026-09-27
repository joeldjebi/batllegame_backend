<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Enums\PerformanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\PreselectionSubmission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * An artist's page in the app: one page per account (all their competitions),
 * reached from any of their participations; their videos come from /feed?artist=.
 */
class ArtistController extends Controller
{
    public function show(Request $request, Participant $participant): JsonResponse
    {
        $artist = $this->artist($participant);
        $viewer = $request->user('sanctum');

        $participations = $artist->participations()
            ->with('competition.organizer')
            ->whereHas('competition', fn ($q) => $q->where('status', '!=', CompetitionStatus::Draft))
            ->latest('id')
            ->get();
        $participantIds = $participations->modelKeys();

        return response()->json([
            'participant_id' => $participant->id,
            'stage_name' => $participant->stage_name,
            'avatar_url' => $artist->avatarUrl(),
            'followers_count' => $artist->followers()->count(),
            'following' => $viewer !== null && $viewer->following()->whereKey($artist->id)->exists(),
            'is_me' => $viewer?->id === $artist->id,
            'performances_count' => Performance::query()->whereIn('participant_id', $participantIds)->where('status', PerformanceStatus::Approved)->count()
                + PreselectionSubmission::query()->whereIn('participant_id', $participantIds)->where('status', PerformanceStatus::Approved)->count(),
            'participations' => $participations->map(fn (Participant $p) => [
                'participant_id' => $p->id,
                'stage_name' => $p->stage_name,
                'status' => $p->status,
                'status_label' => $p->status->label(),
                'competition' => [
                    'slug' => $p->competition->slug,
                    'name' => $p->competition->name,
                    'discipline' => $p->competition->discipline,
                    'status' => $p->competition->status,
                    'organizer' => $p->competition->organizer?->name,
                ],
            ])->values(),
        ]);
    }

    public function follow(Request $request, Participant $participant): Response
    {
        $artist = $this->artist($participant);
        abort_if($artist->id === $request->user()->id, 422, 'Tu ne peux pas te suivre toi-même.');

        $request->user()->following()->syncWithoutDetaching([$artist->id]);

        return response()->noContent();
    }

    public function unfollow(Request $request, Participant $participant): Response
    {
        $request->user()->following()->detach($this->artist($participant)->id);

        return response()->noContent();
    }

    /**
     * The artists the user follows, most recent first (their latest participation).
     */
    public function following(Request $request): JsonResponse
    {
        $artists = $request->user()->following()->orderByPivot('created_at', 'desc')->get();
        $latest = Participant::query()->whereIn('user_id', $artists->modelKeys())
            ->whereHas('competition', fn ($q) => $q->where('status', '!=', CompetitionStatus::Draft))
            ->with('competition')->latest('id')->get()->unique('user_id')->keyBy('user_id');

        return response()->json([
            'data' => $artists->filter(fn (User $a) => $latest->has($a->id))->map(fn (User $a) => [
                'participant_id' => $latest[$a->id]->id,
                'stage_name' => $latest[$a->id]->stage_name,
                'avatar_url' => $a->avatarUrl(),
                'competition' => ['slug' => $latest[$a->id]->competition->slug, 'name' => $latest[$a->id]->competition->name],
            ])->values(),
        ]);
    }

    /**
     * The account behind a participation of a public competition.
     */
    private function artist(Participant $participant): User
    {
        abort_unless($participant->competition?->status->isPublic() && $participant->user, 404);

        return $participant->user;
    }
}
