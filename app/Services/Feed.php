<?php

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\PerformanceStatus;
use App\Http\Controllers\Portal\Fan\PreselectionController as FanPreselectionController;
use App\Models\BattleMatch;
use App\Models\Performance;
use App\Models\PreselectionLike;
use App\Models\PreselectionSubmission;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The mobile « Pour toi » feed: every public performance, newest first — approved
 * pre-selection entries and approved stage submissions / captations — in one stable
 * cursor-paginated stream (published_at, kind, id).
 */
class Feed
{
    public const int DEFAULT_LIMIT = 10;

    public const int MAX_LIMIT = 30;

    /** Kinds of item, also their order at the same second. */
    public const string ENTRY = 'preselection';

    public const string PERFORMANCE = 'performance';

    /**
     * @param  array{competition_id?: ?int, discipline?: ?string}  $filters
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string}
     */
    public function page(?User $viewer, ?string $cursor = null, int $limit = self::DEFAULT_LIMIT, array $filters = []): array
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $after = self::decodeCursor($cursor);

        $rows = DB::query()
            ->fromSub($this->source(self::ENTRY, 'preselection_submissions', $filters)->unionAll($this->source(self::PERFORMANCE, 'performances', $filters)), 'feed')
            ->when($after, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('published_at', '<', $after['t'])
                ->orWhere(fn (Builder $q) => $q->where('published_at', $after['t'])->where('kind', '>', $after['k']))
                ->orWhere(fn (Builder $q) => $q->where('published_at', $after['t'])->where('kind', $after['k'])->where('id', '<', $after['i']))))
            ->orderByDesc('published_at')->orderBy('kind')->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $last = $rows->last();

        return [
            'items' => $this->hydrate($rows, $viewer),
            'next_cursor' => $hasMore && $last ? self::encodeCursor($last) : null,
        ];
    }

    /**
     * @param  array{competition_id?: ?int, discipline?: ?string}  $filters
     */
    private function source(string $kind, string $table, array $filters): Builder
    {
        return DB::table($table)
            ->join('competitions', 'competitions.id', '=', "{$table}.competition_id")
            ->selectRaw("'{$kind}' as kind, {$table}.id, {$table}.published_at")
            ->where("{$table}.status", PerformanceStatus::Approved->value)
            ->whereNotNull("{$table}.published_at")
            ->whereNotNull("{$table}.media_path")
            ->where('competitions.status', '!=', CompetitionStatus::Draft->value)
            ->when($filters['competition_id'] ?? null, fn (Builder $q, int $id) => $q->where('competitions.id', $id))
            ->when($filters['discipline'] ?? null, fn (Builder $q, string $discipline) => $q->where('competitions.discipline', $discipline));
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function hydrate(Collection $rows, ?User $viewer): array
    {
        $ids = $rows->groupBy('kind')->map(fn (Collection $group) => $group->pluck('id')->all());

        $entries = PreselectionSubmission::query()->whereIn('id', $ids[self::ENTRY] ?? [])
            ->with(['participant.user', 'competition', 'preselection.competition'])->get()->keyBy('id');
        $performances = Performance::query()->whereIn('id', $ids[self::PERFORMANCE] ?? [])
            ->with(['participant.user', 'competition', 'stage'])->get()->keyBy('id');

        // The viewer's like per pre-selection (one per competition).
        $myLikes = $viewer && $entries->isNotEmpty()
            ? PreselectionLike::query()->where('user_id', $viewer->id)->whereIn('preselection_id', $entries->pluck('preselection_id')->unique())->pluck('submission_id', 'preselection_id')
            : collect();

        $matches = $this->matchesOf($performances);

        return $rows->map(fn (object $row) => $row->kind === self::ENTRY
            ? ($entries->has($row->id) ? $this->entryItem($entries[$row->id], $myLikes, $viewer) : null)
            : ($performances->has($row->id) ? $this->performanceItem($performances[$row->id], $matches) : null))
            ->filter()->values()->all();
    }

    /**
     * @param  Collection<int|string, int>  $myLikes
     * @return array<string, mixed>
     */
    private function entryItem(PreselectionSubmission $entry, Collection $myLikes, ?User $viewer): array
    {
        $preselection = $entry->preselection;
        $myLike = $myLikes->get($preselection->id);
        $ownEntry = $viewer && $entry->participant->user_id === $viewer->id;

        return [
            ...$this->common(self::ENTRY, $entry),
            'context' => ['label' => 'Présélection', 'entry_id' => $entry->id, 'match_id' => null, 'stage_id' => null],
            'likes' => [
                'enabled' => $preselection->publicVotingEnabled(),
                'open' => $preselection->acceptsLikes(),
                'liked' => $myLike === $entry->id,
                'count' => FanPreselectionController::showsAllCounts($preselection, $myLike) || $ownEntry ? $entry->likes_count : null,
            ],
            'vote' => null,
            'share_url' => route('fan.competitions.preselection.entry', [$entry->competition, $entry]),
        ];
    }

    /**
     * @param  Collection<string, BattleMatch>  $matches
     * @return array<string, mixed>
     */
    private function performanceItem(Performance $performance, Collection $matches): array
    {
        $match = $matches->get($performance->match_id ? "m{$performance->match_id}" : "s{$performance->stage_id}:{$performance->participant_id}");

        return [
            ...$this->common(self::PERFORMANCE, $performance),
            'context' => ['label' => $match?->isGroupMatch() ? ($match->group?->name ?? 'Poule') : ($performance->stage?->name ?? 'Battle'), 'entry_id' => null, 'match_id' => $match?->id, 'stage_id' => $performance->stage_id],
            'likes' => null,
            'vote' => $match ? ['match_id' => $match->id, 'open' => $match->isVotingOpen(), 'closes_at' => $match->voting_closes_at?->toIso8601String()] : null,
            'share_url' => route('fan.competitions.show', $performance->competition).($match ? '#match-'.$match->id : ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function common(string $kind, PreselectionSubmission|Performance $media): array
    {
        $competition = $media->competition;

        return [
            'key' => "{$kind}-{$media->id}",
            'kind' => $kind,
            'id' => $media->id,
            'published_at' => $media->published_at?->toIso8601String(),
            'media' => [
                'type' => $media->media_type,
                'url' => $media->mediaUrl(),
                'poster_url' => $media->posterUrl(),
                'width' => $media->width,
                'height' => $media->height,
                'duration_seconds' => $media->duration_seconds,
            ],
            'artist' => [
                'participant_id' => $media->participant_id,
                'stage_name' => $media->participant?->stage_name,
                'avatar_url' => $media->participant?->user?->avatarUrl(),
            ],
            'competition' => [
                'id' => $competition->id,
                'slug' => $competition->slug,
                'name' => $competition->name,
                'discipline' => $competition->discipline,
                'status' => $competition->status,
            ],
        ];
    }

    /**
     * The match each performance plays in: its captation match, or the match of its
     * stage the artist has a slot in (keys « m{match} » and « s{stage}:{participant} »).
     *
     * @param  Collection<int, Performance>  $performances
     * @return Collection<string, BattleMatch>
     */
    private function matchesOf(Collection $performances): Collection
    {
        if ($performances->isEmpty()) {
            return collect();
        }

        $byId = BattleMatch::query()->whereIn('id', $performances->pluck('match_id')->filter()->unique())->with('group')->get()->toBase()
            ->mapWithKeys(fn (BattleMatch $match) => ["m{$match->id}" => $match]);

        $submissions = $performances->whereNull('match_id');
        $byStage = BattleMatch::query()
            ->whereIn('stage_id', $submissions->pluck('stage_id')->unique())
            ->whereHas('slots', fn ($q) => $q->whereIn('participant_id', $submissions->pluck('participant_id')->unique()))
            ->with(['group', 'slots:id,match_id,participant_id'])
            ->get()->toBase()
            ->flatMap(fn (BattleMatch $match) => $match->slots->whereNotNull('participant_id')
                ->mapWithKeys(fn ($slot) => ["s{$match->stage_id}:{$slot->participant_id}" => $match]));

        return $byId->merge($byStage);
    }

    private static function encodeCursor(object $row): string
    {
        $at = Carbon::parse($row->published_at)->format('Y-m-d H:i:s');

        return rtrim(strtr(base64_encode(json_encode(['t' => $at, 'k' => $row->kind, 'i' => (int) $row->id])), '+/', '-_'), '=');
    }

    /**
     * @return ?array{t: string, k: string, i: int}
     */
    private static function decodeCursor(?string $cursor): ?array
    {
        if (blank($cursor)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        return is_array($data) && isset($data['t'], $data['k'], $data['i']) && in_array($data['k'], [self::ENTRY, self::PERFORMANCE], true)
            && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $data['t'])
            ? ['t' => $data['t'], 'k' => $data['k'], 'i' => (int) $data['i']]
            : null;
    }
}
