<?php

use App\Enums\MatchStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Performance;
use App\Models\PreselectionSubmission;
use App\Services\Competition\GroupStandingsCalculator;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
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

Artisan::command('stages:process', function (StageService $stages) {
    ['forfeits' => $forfeits, 'opened' => $opened] = $stages->processDue();

    $this->info("{$forfeits} forfait(s) appliqué(s), {$opened} vote(s) d'étape ouvert(s).");
})->purpose('Apply forfeits at submission deadlines and open the planned stage votes');

Schedule::command('stages:process')->everyMinute()->withoutOverlapping();
Schedule::command('matches:close-expired')->everyMinute()->withoutOverlapping();
