<?php

use App\Data\CompetitionSettings;
use App\Enums\CompetitionMode;
use App\Enums\OrganizerRole;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Criterion;
use App\Models\Group;
use App\Models\Judge;
use App\Models\JuryScore;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;

it('builds E.164 phone numbers from the country dial code', function () {
    $country = Country::factory()->ivoryCoast();

    expect($country->toE164('07 01 02 03 04'))->toBe('+2250701020304')
        ->and($country->isValidNationalNumber('07 01 02 03 04'))->toBeTrue()
        ->and($country->isValidNationalNumber('0701'))->toBeFalse();
});

it('only lists active countries', function () {
    Country::factory()->ivoryCoast();
    Country::factory()->create(['iso2' => 'SN', 'iso3' => 'SEN', 'dial_code' => '+221', 'is_active' => false]);

    expect(Country::query()->active()->pluck('iso2')->all())->toBe(['CI']);
});

it('resolves the role of a user inside an organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Admin, $user)->create();
    $other = Organizer::factory()->create();

    expect($user->roleIn($organizer))->toBe(OrganizerRole::Admin)
        ->and($user->isMemberOf($other))->toBeFalse()
        ->and($user->organizers()->first()->membership->role)->toBe(OrganizerRole::Admin);
});

it('grants the platform admin role through spatie, not organizer membership', function () {
    $admin = User::factory()->platformAdmin()->create();

    expect($admin->isPlatformAdmin())->toBeTrue()
        ->and($admin->organizerMemberships)->toBeEmpty()
        ->and(User::factory()->create()->isPlatformAdmin())->toBeFalse();
});

it('casts competition settings with defaults', function () {
    $competition = Competition::factory()->create(['settings' => null]);

    expect($competition->fresh()->settings)
        ->toBeInstanceOf(CompetitionSettings::class)
        ->timezone->toBe('Africa/Abidjan')
        ->publicVotingEnabled->toBeTrue();
});

it('falls back to the competition mode when a phase does not override it', function () {
    $competition = Competition::factory()->create(['mode' => CompetitionMode::Hybrid]);
    $inherited = Phase::factory()->for($competition)->create(['mode' => null]);
    $overridden = Phase::factory()->for($competition)->create(['mode' => CompetitionMode::Online]);

    expect($inherited->effectiveMode())->toBe(CompetitionMode::Hybrid)
        ->and($overridden->effectiveMode())->toBe(CompetitionMode::Online)
        ->and($overridden->position)->toBe(2);
});

it('derives the denormalized competition_id of a match from its phase', function () {
    $phase = Phase::factory()->create();

    $match = BattleMatch::factory()->create(['phase_id' => $phase->id]);

    expect($match->competition_id)->toBe($phase->competition_id);
});

it('rejects a match attached to another competition than its phase', function () {
    $phase = Phase::factory()->create();
    $otherCompetition = Competition::factory()->create();

    BattleMatch::factory()->create(['phase_id' => $phase->id, 'competition_id' => $otherCompetition->id]);
})->throws(LogicException::class);

it('derives competition_id of jury scores and public votes from the match', function () {
    $match = BattleMatch::factory()->create();
    $competition = $match->competition;
    $participant = Participant::factory()->for($competition)->create();
    $judge = Judge::factory()->for($competition)->create();
    $criterion = Criterion::factory()->for($competition)->create();

    $score = new JuryScore(['match_id' => $match->id, 'judge_id' => $judge->id, 'participant_id' => $participant->id, 'criterion_id' => $criterion->id, 'score' => 8]);
    $score->save();

    $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $participant->id]);
    $vote->user_id = User::factory()->create()->id;
    $vote->save();

    expect($score->competition_id)->toBe($competition->id)
        ->and($vote->competition_id)->toBe($competition->id);
});

it('links matches, slots, groups and standings', function () {
    $group = Group::factory()->create();
    $competition = $group->phase->competition;
    [$a, $b] = Participant::factory()->for($competition)->count(2)->create();
    $group->participants()->attach([$a->id, $b->id]);

    $final = BattleMatch::factory()->create(['phase_id' => $group->phase_id, 'round' => 2]);
    $match = BattleMatch::factory()->create([
        'phase_id' => $group->phase_id,
        'group_id' => $group->id,
        'next_match_id' => $final->id,
        'next_match_slot' => 1,
    ]);
    $match->participants()->attach($a->id, ['slot' => 1]);
    $match->participants()->attach($b->id, ['slot' => 2]);

    expect($match->slots->pluck('participant_id')->all())->toBe([$a->id, $b->id])
        ->and($match->nextMatch->is($final))->toBeTrue()
        ->and($final->feederMatches->pluck('id')->all())->toBe([$match->id])
        ->and($group->standings)->toHaveCount(2)
        ->and($a->groups->first()->standing->points)->toBe(0)
        ->and($competition->matches)->toHaveCount(2);
});
