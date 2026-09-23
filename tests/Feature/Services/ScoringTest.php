<?php

use App\Enums\MatchStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Models\BattleMatch;
use App\Models\Criterion;
use App\Models\Judge;
use App\Models\JuryScore;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\MatchScoreCalculator;

require_once __DIR__.'/helpers.php';

/**
 * A two-participant match in a phase with the given rules.
 *
 * @param  array<string, mixed>  $rules
 * @return array{0: BattleMatch, 1: Participant, 2: Participant}
 */
function scoredMatch(array $rules, PhaseType $type = PhaseType::SingleElimination): array
{
    [$competition, $participants] = competitionWithParticipants(2);
    $phase = Phase::factory()->for($competition)->started()->create(['type' => $type, 'rules' => $rules, 'qualifiers_per_group' => 1]);

    $match = new BattleMatch(['phase_id' => $phase->id, 'round' => 1, 'bracket_position' => 1]);
    $match->save();
    $match->forceFill(['status' => MatchStatus::Voting])->save();
    $match->slots()->createMany([
        ['slot' => 1, 'participant_id' => $participants[0]->id],
        ['slot' => 2, 'participant_id' => $participants[1]->id],
    ]);

    return [$match, $participants[0], $participants[1]];
}

function juryScore(BattleMatch $match, Judge $judge, Participant $participant, Criterion $criterion, float $score): void
{
    JuryScore::create([
        'match_id' => $match->id, 'judge_id' => $judge->id, 'participant_id' => $participant->id,
        'criterion_id' => $criterion->id, 'score' => $score,
    ]);
}

function publicVotes(BattleMatch $match, Participant $participant, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $participant->id]);
        $vote->user_id = User::factory()->create()->id;
        $vote->save();
    }
}

it('normalizes the jury score on 100 with weighted criteria and averages the judges', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'jury']);
    $competition = $match->competition;
    $flow = Criterion::factory()->for($competition)->create(['max_points' => 10, 'weight' => 1]);
    $lyrics = Criterion::factory()->for($competition)->create(['max_points' => 20, 'weight' => 3]);
    [$judge1, $judge2] = Judge::factory()->for($competition)->count(2)->create();

    // Judge 1 on A: (1 × 8/10 + 3 × 10/20) / 4 = 0.575 → 57.5
    juryScore($match, $judge1, $a, $flow, 8);
    juryScore($match, $judge1, $a, $lyrics, 10);
    // Judge 2 on A: (1 × 10/10 + 3 × 20/20) / 4 = 1 → 100
    juryScore($match, $judge2, $a, $flow, 10);
    juryScore($match, $judge2, $a, $lyrics, 20);
    // Only judge 1 scored B: (1 × 5/10 + 3 × 5/20) / 4 = 0.3125 → 31.25
    juryScore($match, $judge1, $b, $flow, 5);
    juryScore($match, $judge1, $b, $lyrics, 5);

    $scores = app(MatchScoreCalculator::class)->calculate($match);

    expect($scores[$a->id])->toBe(['jury' => 78.75, 'public' => null, 'final' => 78.75])
        ->and($scores[$b->id])->toBe(['jury' => 31.25, 'public' => null, 'final' => 31.25]);
});

it('computes the public score as the share of the match votes', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'public']);
    publicVotes($match, $a, 3);
    publicVotes($match, $b, 1);

    $scores = app(MatchScoreCalculator::class)->calculate($match);

    expect($scores[$a->id]['public'])->toBe(75.0)
        ->and($scores[$b->id]['public'])->toBe(25.0);
});

it('splits the public score evenly when nobody voted', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'public']);

    $scores = app(MatchScoreCalculator::class)->calculate($match);

    expect($scores[$a->id]['public'])->toBe(50.0)->and($scores[$b->id]['public'])->toBe(50.0);
});

it('combines jury and public scores with the phase weights and stores them', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'mixte', 'jury_weight' => 70, 'public_weight' => 30]);
    $criterion = Criterion::factory()->for($match->competition)->create(['max_points' => 10]);
    $judge = Judge::factory()->for($match->competition)->create();
    juryScore($match, $judge, $a, $criterion, 6); // 60
    juryScore($match, $judge, $b, $criterion, 8); // 80
    publicVotes($match, $a, 9);                    // 90
    publicVotes($match, $b, 1);                    // 10

    $closed = app(MatchCloser::class)->close($match);

    // A: 60 × 0.7 + 90 × 0.3 = 69 ; B: 80 × 0.7 + 10 × 0.3 = 59
    expect($closed->winner_id)->toBe($a->id)
        ->and($match->slots()->where('participant_id', $a->id)->first()->only(['jury_score', 'public_score', 'final_score']))
        ->toBe(['jury_score' => 60.0, 'public_score' => 90.0, 'final_score' => 69.0])
        ->and($match->slots()->where('participant_id', $b->id)->value('final_score'))->toEqual(59.0);
});

it('refuses to close a match the jury has not scored', function () {
    [$match] = scoredMatch(['vote_mode' => 'mixte']);

    app(MatchCloser::class)->close($match);
})->throws(CompetitionFlowException::class, "Le jury n'a pas encore noté");

it('breaks a tie with the phase tie breakers', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'mixte', 'tie_breakers' => ['jury', 'seed']]);
    $criterion = Criterion::factory()->for($match->competition)->create(['max_points' => 10]);
    $judge = Judge::factory()->for($match->competition)->create();
    juryScore($match, $judge, $a, $criterion, 6); // 60 × 0.5 + 40 × 0.5 = 50
    juryScore($match, $judge, $b, $criterion, 4); // 40 × 0.5 + 60 × 0.5 = 50
    publicVotes($match, $a, 2);
    publicVotes($match, $b, 3);

    expect(app(MatchCloser::class)->close($match)->winner_id)->toBe($a->id);
});

it('falls back to the best seed when requested', function () {
    [$match, $a] = scoredMatch(['vote_mode' => 'public', 'tie_breakers' => ['public', 'seed']]);

    expect(app(MatchCloser::class)->close($match)->winner_id)->toBe($a->id);
});

it('asks for a manual decision on a perfect tie', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'public', 'tie_breakers' => ['public']]);

    expect(fn () => app(MatchCloser::class)->close($match))->toThrow(CompetitionFlowException::class);
    expect(app(MatchCloser::class)->close($match, forcedWinnerId: $b->id)->winner_id)->toBe($b->id);
});

it('records a draw in a group match when draws are allowed', function () {
    [$match] = scoredMatch(['vote_mode' => 'public', 'allow_draws' => true, 'group_count' => 1], PhaseType::Groups);
    $group = $match->phase->groups()->create(['name' => 'Poule A']);
    $match->forceFill(['group_id' => $group->id])->save();

    $closed = app(MatchCloser::class)->close($match);

    expect($closed->status)->toBe(MatchStatus::Closed)->and($closed->winner_id)->toBeNull();
});

it('is idempotent when a match is closed twice', function () {
    [$match, $a] = scoredMatch(['vote_mode' => 'public']);
    publicVotes($match, $a, 1);

    $first = app(MatchCloser::class)->close($match);
    $second = app(MatchCloser::class)->close($match);

    expect($second->closed_at->equalTo($first->closed_at))->toBeTrue();
});

it('recomputes stored scores from the source tables', function () {
    [$match, $a, $b] = scoredMatch(['vote_mode' => 'public']);
    publicVotes($match, $a, 1);
    app(MatchCloser::class)->close($match);

    // Corrupt the denormalized value, then rebuild it.
    $match->slots()->update(['public_score' => 0, 'final_score' => 0]);
    $this->artisan('scores:recompute', ['competition' => $match->competition->slug])->assertSuccessful();

    expect($match->slots()->where('participant_id', $a->id)->value('final_score'))->toEqual(100.0);
});
