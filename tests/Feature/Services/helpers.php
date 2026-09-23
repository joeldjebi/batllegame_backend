<?php

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\PhaseType;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\PhaseLauncher;
use Illuminate\Support\Collection;

/**
 * A running competition with $count validated participants seeded 1..$count.
 *
 * @return array{0: Competition, 1: Collection<int, Participant>}
 */
function competitionWithParticipants(int $count): array
{
    $competition = Competition::factory()->create(['status' => CompetitionStatus::Registration]);
    $participants = collect(range(1, $count))->map(
        fn (int $seed) => Participant::factory()->for($competition)->create(['seed' => $seed, 'stage_name' => "Seed {$seed}"])
    );

    return [$competition, $participants];
}

/**
 * Create and start a phase decided by the public only (simplest to simulate).
 *
 * @param  array<string, mixed>  $rules
 */
function startPhase(Competition $competition, PhaseType $type, array $rules = [], ?int $qualifiersPerGroup = null): Phase
{
    $phase = Phase::factory()->for($competition)->create([
        'type' => $type,
        'qualifiers_per_group' => $qualifiersPerGroup,
        'rules' => ['vote_mode' => 'public', ...$rules],
    ]);

    return app(PhaseLauncher::class)->start($phase);
}

/**
 * Play a match: the given participant gets one public vote more than the other.
 */
function playMatch(BattleMatch $match, ?Participant $winner): BattleMatch
{
    $match->refresh()->forceFill(['status' => MatchStatus::Voting])->save();
    $ids = $match->slots()->pluck('participant_id');

    foreach ($winner ? [$winner->id] : $ids->all() as $participantId) {
        $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $participantId]);
        $vote->user_id = User::factory()->create()->id;
        $vote->save();
    }

    return app(MatchCloser::class)->close($match);
}

/**
 * Play every playable match of the phase, the best seed (lowest number) always winning.
 */
function playPhaseByFavorites(Phase $phase, ?callable $pickWinner = null): void
{
    $pickWinner ??= fn (Collection $pair) => $pair->sortBy('seed')->first();

    while ($match = nextPlayableMatch($phase)) {
        $pair = Participant::query()->whereIn('id', $match->slots()->pluck('participant_id'))->get();
        playMatch($match, $pickWinner($pair, $match));
    }
}

function nextPlayableMatch(Phase $phase): ?BattleMatch
{
    return $phase->matches()
        ->where('status', MatchStatus::Scheduled)
        ->whereDoesntHave('slots', fn ($q) => $q->whereNull('participant_id'))
        ->orderBy('bracket')->orderBy('round')->orderBy('bracket_position')
        ->first();
}

/**
 * @return list<int|null> Participant seeds of a match, by slot.
 */
function seedsOf(BattleMatch $match): array
{
    return $match->slots()->with('participant')->orderBy('slot')->get()
        ->map(fn ($slot) => $slot->participant?->seed)->all();
}
