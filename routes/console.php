<?php

use App\Enums\MatchStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Services\Competition\GroupStandingsCalculator;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('matches:close-expired', function (MatchCloser $closer) {
    $matches = BattleMatch::query()
        ->where('status', MatchStatus::Voting)
        ->whereNotNull('voting_closes_at')
        ->where('voting_closes_at', '<=', now())
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
})->purpose('Close the matches whose voting window has ended');

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

Artisan::command('stages:process', function (StageService $stages) {
    ['forfeits' => $forfeits, 'opened' => $opened] = $stages->processDue();

    $this->info("{$forfeits} forfait(s) appliqué(s), {$opened} vote(s) d'étape ouvert(s).");
})->purpose('Apply forfeits at submission deadlines and open the planned stage votes');

Schedule::command('stages:process')->everyMinute()->withoutOverlapping();
Schedule::command('matches:close-expired')->everyMinute()->withoutOverlapping();
