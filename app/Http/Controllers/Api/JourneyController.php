<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhaseType;
use App\Enums\PreselectionState;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Performance;
use App\Models\Stage;
use App\Services\ArtistJourney;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Mon parcours » of the mobile app: the same journey as the /artiste page — the
 * pre-selection, every phase and stage up to the final, my group or opponent, my
 * performance per stage, the results, and the one next thing to do.
 */
class JourneyController extends Controller
{
    public function show(Request $request, Competition $competition, ArtistJourney $journey): JsonResponse
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)
            ->with(['preselectionEntry', 'payments', 'user'])->firstOr(fn () => abort(404));
        $competition->load(['organizer', 'preselection']);

        ['phases' => $phases, 'next' => $next, 'out' => $out, 'champion' => $champion] = $journey->for($participant);

        return response()->json([
            'data' => [
                'competition' => ['id' => $competition->id, 'slug' => $competition->slug, 'name' => $competition->name, 'status' => $competition->status, 'organizer' => $competition->organizer->name],
                'participant' => [
                    'id' => $participant->id,
                    'stage_name' => $participant->stage_name,
                    'status' => $participant->status,
                    'paid' => $participant->hasPaid(),
                    'payment_required' => $competition->requiresPayment() && ! $participant->hasPaid(),
                    'awaits_approval' => $participant->awaitsApproval(),
                ],
                'out' => $out,
                'champion' => $champion,
                'next' => $next ? [
                    'type' => $next['type'],
                    'text' => $next['text'],
                    'stage' => $next['stage'],
                    'phase' => $next['phase'],
                    'deadline' => self::date($next['deadline'] ?? null),
                    'stage_id' => isset($next['stageModel']) ? $next['stageModel']->id : null,
                    'match_id' => isset($next['match']) ? $next['match']->id : null,
                ] : null,
                'preselection' => $this->preselection($participant, $competition),
                'phases' => array_map(fn (array $row) => [
                    'id' => $row['phase']->id,
                    'position' => $row['phase']->position,
                    'type' => $row['phase']->type,
                    'title' => 'Phase '.$row['phase']->position.' · '.($row['phase']->type === PhaseType::Groups ? 'Poules' : $row['phase']->type->label()),
                    'online' => ArtistJourney::isOnline($row['phase']),
                    'qualifiers_per_group' => $row['phase']->type === PhaseType::Groups ? $row['phase']->qualifiers_per_group : null,
                    'vote_mode' => $row['phase']->rules->voteMode,
                    'state' => $row['state'],
                    'stages' => array_map(fn (array $stage) => $this->stage($stage, $participant), $row['stages']),
                ], $phases),
            ],
        ]);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function preselection(Participant $participant, Competition $competition): ?array
    {
        $preselection = $competition->preselection;
        if ($preselection === null) {
            return null;
        }

        $state = $preselection->state();
        $entry = $participant->preselectionEntry;
        $published = $state === PreselectionState::Published;

        return [
            'state' => $state,
            'ends_at' => self::date($preselection->ends_at),
            'selection_size' => $preselection->rules->selectionSize,
            'can_submit' => $preselection->isOpen() && $participant->canEnterPreselection(),
            'media_rules' => [
                'types' => $preselection->rules->mediaTypes,
                'max_duration_seconds' => $preselection->rules->mediaMaxDuration,
                'max_size_mb' => $preselection->rules->mediaMaxSizeMb,
            ],
            'entry' => $entry ? [
                'id' => $entry->id,
                'status' => $entry->status,
                'rejection_reason' => $entry->rejection_reason,
                'likes' => $entry->likes_count,
                'media' => self::media($entry),
            ] : null,
            'result' => $published ? ['selected' => (bool) $entry?->selected, 'rank' => $entry?->rank] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stage(array $row, Participant $participant): array
    {
        /** @var ?BattleMatch $match */
        $match = $row['match'];
        /** @var ?Stage $stage */
        $stage = $row['stage'];
        /** @var ?Performance $submission */
        $submission = $row['submission'];
        $slot = $row['slot'] ?? null;
        $action = $row['action'];

        return [
            'id' => $stage?->id,
            'name' => $row['name'],
            'state' => $row['state'],
            'label' => $row['label'],
            'dates' => array_map(fn ($date) => self::date($date), $row['dates']),
            'action' => $action ? ['type' => $action['type'], 'text' => $action['text'], 'deadline' => self::date($action['deadline'] ?? null)] : null,
            'media_rules' => $stage && in_array($action['type'] ?? null, ['submit', 'sent'], true) ? [
                'types' => $stage->phase->rules->mediaTypes,
                'max_duration_seconds' => $stage->phase->rules->mediaMaxDuration,
                'max_size_mb' => $stage->phase->rules->mediaMaxSizeMb,
            ] : null,
            'match' => $match ? [
                'id' => $match->id,
                'is_group' => $match->isGroupMatch(),
                'title' => $match->title(),
                'others' => ArtistJourney::others($match, $participant)->map(fn (Participant $other) => [
                    'participant_id' => $other->id,
                    'stage_name' => $other->stage_name,
                    'avatar_url' => $other->user?->avatarUrl(),
                ])->values(),
                'my_score' => $slot?->final_score !== null && $match->resultsArePublic() ? (float) $slot->final_score : null,
                'my_rank' => $match->resultsArePublic() ? $slot?->rank : null,
                'voting_open' => $match->isVotingOpen(),
            ] : null,
            'submission' => $submission?->media_path ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'rejection_reason' => $submission->rejection_reason,
                'media' => self::media($submission),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function media(object $media): array
    {
        return ['type' => $media->media_type, 'url' => $media->mediaUrl(), 'poster_url' => $media->posterUrl(), 'width' => $media->width, 'height' => $media->height, 'duration_seconds' => $media->duration_seconds];
    }

    private static function date(mixed $date): ?string
    {
        return $date instanceof CarbonInterface ? $date->toIso8601String() : null;
    }
}
