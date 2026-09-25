<?php

use App\Enums\MatchStatus;
use App\Enums\PerformanceStatus;
use App\Enums\PlatformRole;
use App\Exceptions\CompetitionFlowException;
use App\Jobs\OptimizeMedia;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Performance;
use App\Models\PreselectionSubmission;
use App\Models\User;
use App\Services\Competition\GroupStandingsCalculator;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use App\Services\DemoDataPurger;
use App\Services\Media\MediaOptimization;
use App\Services\Media\MediaProvenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('matches:close-expired', function (MatchCloser $closer) {
    // Closed at the end of the jury deliberation (or of the vote when there is none).
    $matches = BattleMatch::query()
        ->where('status', MatchStatus::Voting)
        ->where(fn ($q) => $q
            ->where('deliberation_ends_at', '<=', now())
            ->orWhere(fn ($q) => $q->whereNull('deliberation_ends_at')->whereNotNull('voting_closes_at')->where('voting_closes_at', '<=', now())))
        ->get();

    foreach ($matches as $match) {
        try {
            $closer->close($match);
            $this->info("Match #{$match->id} closed.");
        } catch (CompetitionFlowException $e) {
            // Missing jury scores or a perfect tie: the organizer has to act.
            Log::warning("Match #{$match->id} could not be closed automatically: {$e->getMessage()}");
            $this->warn("Match #{$match->id}: {$e->getMessage()}");
        }
    }
})->purpose('Close the matches whose vote and jury deliberation have ended');

Artisan::command('scores:recompute {competition : Competition slug}', function (string $competition, MatchCloser $closer, GroupStandingsCalculator $standings) {
    $competition = Competition::query()->where('slug', $competition)->firstOrFail();

    $matches = $competition->matches()->where('status', MatchStatus::Closed)->with('slots')->get()
        ->filter(fn (BattleMatch $match) => $match->slots->whereNotNull('participant_id')->count() === 2);

    $matches->each(fn (BattleMatch $match) => $closer->storeScores($match));

    $competition->phases()->with('groups')->get()
        ->flatMap->groups
        ->each(fn ($group) => $standings->recalculate($group));

    $this->info("Recomputed {$matches->count()} matches of {$competition->name}.");
})->purpose('Rebuild denormalized scores and standings from jury scores and public votes');

Artisan::command('media:provenance {--force : Re-analyze media already analyzed}', function (MediaProvenance $provenance) {
    $count = 0;

    foreach ([Performance::class, PreselectionSubmission::class] as $model) {
        $model::query()->whereNotNull('media_path')
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('media_origin'))
            ->lazyById()
            ->each(function ($media) use ($provenance, &$count): void {
                $disk = Storage::disk($media->media_disk ?? config('media.disk'));

                if (config('filesystems.disks.'.($media->media_disk ?? config('media.disk')).'.driver') !== 'local' || ! $disk->exists($media->media_path)) {
                    return;
                }

                $result = $provenance->analyze($disk->path($media->media_path));
                $media->forceFill([
                    'recorded_at' => $result['recorded_at'],
                    'media_origin' => $result['origin'],
                    'media_metadata' => [...$result['metadata'], 'uploaded_at' => $media->media_metadata['uploaded_at'] ?? $media->updated_at?->toIso8601String()],
                ])->saveQuietly();
                $count++;
            });
    }

    $this->info("{$count} média(s) analysé(s).");
})->purpose('Read the hidden metadata (recording date, origin) of media uploaded before the analysis existed');

Artisan::command('media:optimize {--limit=500 : Maximum number of media queued} {--sync : Process now instead of queueing}', function () {
    if (! MediaOptimization::enabled()) {
        $this->warn('Optimisation désactivée (MEDIA_OPTIMIZE=false).');

        return;
    }

    $count = 0;
    $limit = max(1, (int) $this->option('limit'));

    foreach ([Performance::class, PreselectionSubmission::class] as $model) {
        $model::query()->whereNotNull('media_path')->whereNull('optimized_at')
            ->where('status', '!=', PerformanceStatus::Processing)
            ->lazyById()
            ->each(function ($media) use (&$count, $limit): bool {
                if ($count >= $limit) {
                    return false;
                }

                $this->option('sync') ? OptimizeMedia::dispatchSync($media) : OptimizeMedia::dispatch($media);
                $count++;

                return true;
            });
    }

    $this->info($this->option('sync') ? "{$count} média(s) traité(s)." : "{$count} média(s) mis en file d'attente (worker requis).");
})->purpose('Optimize for streaming (poster, faststart) the media uploaded before optimization existed');

Artisan::command('stages:process', function (StageService $stages) {
    ['forfeits' => $forfeits, 'opened' => $opened] = $stages->processDue();

    $this->info("{$forfeits} forfait(s) appliqué(s), {$opened} vote(s) d'étape ouvert(s).");
})->purpose('Apply forfeits at submission deadlines and open the planned stage votes');

Schedule::command('stages:process')->everyMinute()->withoutOverlapping();
Schedule::command('matches:close-expired')->everyMinute()->withoutOverlapping();

Artisan::command('demo:purge {--accounts : Also remove the test organizer and the SEED_* test accounts} {--force : Really delete (without it: preview only)}', function (DemoDataPurger $purger) {
    $withAccounts = (bool) $this->option('accounts');
    $preview = $purger->preview($withAccounts);

    $this->table(['Compétitions', 'Organisateurs', 'Comptes', 'Comptes conservés (utilisés ailleurs)'], [array_values($preview)]);

    // No interactive confirmation: a prompt fed by a pipe or a script must never delete anything.
    if (! $this->option('force')) {
        $this->warn('Aperçu uniquement. Relancez avec --force pour supprimer définitivement ces données.');

        return;
    }

    $result = $purger->purge($withAccounts);
    $this->info("Supprimé : {$result['competitions']} compétition(s), {$result['organizers']} organisateur(s), {$result['users']} compte(s), {$result['files']} fichier(s).");
})->purpose('Remove the data created by the local seeders');

Artisan::command('judges:default-password {--force : Really apply (without it: preview only)}', function () {
    $password = config('accounts.judge_default_password');

    if (blank($password) || app()->isProduction()) {
        $this->error('Définissez JUDGE_DEFAULT_PASSWORD dans .env (jamais en production).');

        return 1;
    }

    // Judge accounts only: never a platform admin nor a back-office account (their login is their own).
    $judges = User::query()->whereHas('judgeAssignments')
        ->whereDoesntHave('organizerMemberships')
        ->whereDoesntHave('roles', fn ($q) => $q->where('name', PlatformRole::Admin->value))
        ->get();

    $this->table(['Juré', 'Téléphone'], $judges->map(fn (User $u) => [$u->name, $u->phone])->all());

    if (! $this->option('force')) {
        $this->warn('Aperçu uniquement. Relancez avec --force pour appliquer le mot de passe par défaut.');

        return 0;
    }

    // Changed at their next login, like any temporary password.
    $judges->each(fn (User $u) => $u->forceFill(['password' => $password, 'must_change_password' => true])->save());
    $this->info("{$judges->count()} juré(s) : mot de passe par défaut appliqué, à changer à la prochaine connexion.");
})->purpose('Give every judge account the default temporary password (local, until SMS delivery)');
