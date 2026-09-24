<?php

namespace App\Services;

use App\Enums\PlatformRole;
use App\Enums\SeedKind;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the data created by the local seeders (tagged with seed_kind).
 *
 * Demo: the demo competitions (with everything in them: registrations, matches, votes,
 * payments, media files) and the generated accounts. With $withAccounts, also the test
 * organizer (and all its competitions) and the SEED_* test accounts.
 *
 * Never removed: platform admins, and any tagged account still active elsewhere
 * (registered, judge or member of a real competition or organizer).
 * Plain queries: no model events, so no realtime burst and no notifications.
 */
class DemoDataPurger
{
    /**
     * @return array{competitions: int, organizers: int, users: int, kept_users: int}
     */
    public function preview(bool $withAccounts = false): array
    {
        $competitionIds = $this->competitionIds($withAccounts);
        $users = $this->users($withAccounts, $competitionIds);

        return [
            'competitions' => $competitionIds->count(),
            'organizers' => $withAccounts ? Organizer::query()->where('seed_kind', SeedKind::TestAccount)->count() : 0,
            'users' => $users['removable']->count(),
            'kept_users' => $users['kept']->count(),
        ];
    }

    /**
     * @return array{competitions: int, organizers: int, users: int, kept_users: int, files: int}
     */
    public function purge(bool $withAccounts = false, ?User $by = null): array
    {
        $files = collect();

        $result = DB::transaction(function () use ($withAccounts, $by, &$files): array {
            $competitionIds = $this->competitionIds($withAccounts);
            $users = $this->users($withAccounts, $competitionIds, except: $by);
            $organizers = $withAccounts ? Organizer::query()->where('seed_kind', SeedKind::TestAccount)->pluck('id') : collect();
            $files = $this->mediaFiles($competitionIds);

            $this->deleteCompetitions($competitionIds);

            if ($organizers->isNotEmpty()) {
                Organizer::query()->whereIn('id', $organizers)->delete();
            }

            $this->deleteUsers($users['removable']);

            return [
                'competitions' => $competitionIds->count(),
                'organizers' => $organizers->count(),
                'users' => $users['removable']->count(),
                'kept_users' => $users['kept']->count(),
            ];
        });

        // Files last: a failed transaction keeps them.
        $files->each(fn (array $file) => rescue(fn () => Storage::disk($file[0] ?? config('media.disk'))->delete($file[1]), report: false));

        Log::info('Seeded data purged', [...$result, 'with_accounts' => $withAccounts, 'by' => $by?->id]);

        return [...$result, 'files' => $files->count()];
    }

    /**
     * @return Collection<int, int>
     */
    private function competitionIds(bool $withAccounts): Collection
    {
        return Competition::query()->withTrashed()
            ->where(fn (Builder $q) => $q
                ->where('seed_kind', SeedKind::Demo)
                ->when($withAccounts, fn (Builder $q) => $q->orWhereIn('organizer_id', Organizer::query()->where('seed_kind', SeedKind::TestAccount)->select('id'))))
            ->pluck('id');
    }

    /**
     * Tagged accounts, split between those that can go and those still used outside the purged data.
     *
     * @param  Collection<int, int>  $competitionIds
     * @return array{removable: Collection<int, int>, kept: Collection<int, int>}
     */
    private function users(bool $withAccounts, Collection $competitionIds, ?User $except = null): array
    {
        $kinds = $withAccounts ? [SeedKind::Demo, SeedKind::TestAccount] : [SeedKind::Demo];
        $organizerIds = $withAccounts ? Organizer::query()->where('seed_kind', SeedKind::TestAccount)->pluck('id') : collect();

        $candidates = User::query()->whereIn('seed_kind', $kinds)
            ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', PlatformRole::Admin->value))
            ->when($except, fn (Builder $q) => $q->whereKeyNot($except->id))
            ->pluck('id');

        $usedElsewhere = collect(['participants', 'judges', 'payments', 'public_votes', 'preselection_likes'])
            ->flatMap(fn (string $table) => DB::table($table)->whereIn('user_id', $candidates)->whereNotIn('competition_id', $competitionIds)->distinct()->pluck('user_id'))
            ->merge(DB::table('organizer_members')->whereIn('user_id', $candidates)->whereNotIn('organizer_id', $organizerIds)->pluck('user_id'))
            ->unique();

        return [
            'removable' => $candidates->diff($usedElsewhere)->values(),
            'kept' => $candidates->intersect($usedElsewhere)->values(),
        ];
    }

    /**
     * @param  Collection<int, int>  $competitionIds
     * @return Collection<int, array{0: ?string, 1: string}>
     */
    private function mediaFiles(Collection $competitionIds): Collection
    {
        return collect(['performances', 'preselection_submissions'])
            ->flatMap(fn (string $table) => DB::table($table)->whereIn('competition_id', $competitionIds)->whereNotNull('media_path')->get(['media_disk', 'media_path']))
            ->map(fn (object $row) => [$row->media_disk, $row->media_path]);
    }

    /**
     * Rows restricting the delete of judges, criteria and users go first; the rest cascades.
     *
     * @param  Collection<int, int>  $ids
     */
    private function deleteCompetitions(Collection $ids): void
    {
        foreach ($ids->chunk(200) as $chunk) {
            DB::table('preselection_scores')->whereIn('submission_id', DB::table('preselection_submissions')->whereIn('competition_id', $chunk)->select('id'))->delete();

            foreach (['jury_scores', 'public_votes', 'preselection_likes', 'payments'] as $table) {
                DB::table($table)->whereIn('competition_id', $chunk)->delete();
            }

            DB::table('competitions')->whereIn('id', $chunk)->delete();
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function deleteUsers(Collection $ids): void
    {
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $chunk)->delete();
            DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $chunk)->delete();
            DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $chunk)->delete();
            DB::table('sessions')->whereIn('user_id', $chunk)->delete();
            DB::table('users')->whereIn('id', $chunk)->delete();
        }
    }
}
