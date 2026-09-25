<?php

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerRole;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\User;
use App\Services\Competition\GroupPlan;
use App\Services\Competition\PhaseLauncher;

it('splits entrants into balanced groups', function () {
    expect(GroupPlan::sizes(16, 4))->toBe([4, 4, 4, 4])
        ->and(GroupPlan::sizes(10, 3))->toBe([4, 3, 3])
        ->and(GroupPlan::sizes(7, 2))->toBe([4, 3]);
});

it('rejects formats that cannot be played', function () {
    expect(GroupPlan::problems(16, 4, 2))->toBe([])
        ->and(GroupPlan::problems(10, 3, 2))->toBe([])
        ->and(GroupPlan::problems(10, 6, 1)[0])->toContain('5 au maximum')
        ->and(GroupPlan::problems(10, 3, 3)[0])->toContain('qualifiez-en 2 au maximum')
        ->and(GroupPlan::problems(8, 4, 2)[0])->toContain('Trop de qualifiés');
});

it('guesses the participants of a new phase', function () {
    $competition = Competition::factory()->create(['max_participants' => 24]);
    expect(GroupPlan::expectedEntrants($competition))->toBe(24);

    Phase::factory()->for($competition)->create(['type' => PhaseType::Groups, 'qualifiers_per_group' => 2, 'rules' => ['group_count' => 4]]);
    expect(GroupPlan::expectedEntrants($competition))->toBe(8);
});

it('checks the group format against the expected participants when saving the phase', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create();
    $url = route('organizers.competitions.phases.store', [$organizer, $competition]);
    $phase = fn (int $groups, int $qualifiers) => ['type' => 'poules', 'mode' => 'en_ligne', 'qualifiers_per_group' => $qualifiers, 'rules' => ['group_count' => $groups, 'expected_entrants' => 12, 'vote_mode' => 'jury']];

    $this->actingAs($owner, 'web')->post($url, $phase(4, 3))->assertSessionHasErrors('rules.group_count');
    $this->actingAs($owner, 'web')->post($url, $phase(4, 2))->assertSessionHasNoErrors();

    expect($competition->phases()->first()->rules->expectedEntrants)->toBe(12);
});

it('fits the planned format to the real participants', function () {
    // 16 artists planned in 4 groups: same format.
    expect(GroupPlan::fit(16, 4, 2, 16))->toBe(['groups' => 4, 'qualifiers' => 2])
        // Planned for 100 in 25 groups of 4, 16 came: 4 groups of 4.
        ->and(GroupPlan::fit(16, 25, 2, 100))->toBe(['groups' => 4, 'qualifiers' => 2])
        // Too many qualifiers for the groups left: fewer groups, qualifiers kept.
        ->and(GroupPlan::fit(6, 3, 2))->toBe(['groups' => 2, 'qualifiers' => 2])
        // 3 artists: one group, the qualifiers capped.
        ->and(GroupPlan::fit(3, 4, 3, 16))->toBe(['groups' => 1, 'qualifiers' => 2])
        ->and(GroupPlan::fit(1, 2, 1))->toBeNull();

    foreach ([[16, 25, 2, 100], [7, 8, 3, 32], [5, 2, 4, null], [2, 10, 1, 40]] as [$n, $g, $q, $planned]) {
        $format = GroupPlan::fit($n, $g, $q, $planned);
        expect(GroupPlan::problems($n, $format['groups'], $format['qualifiers']))->toBe([]);
    }
});

it('adapts the groups to the real participants when the phase starts', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->withMember(OrganizerRole::Owner, $owner)->create();
    $competition = Competition::factory()->for($organizer)->create(['status' => CompetitionStatus::Registration]);
    Participant::factory()->for($competition)->count(16)->create(['status' => ParticipantStatus::Validated]);
    $phase = Phase::factory()->for($competition)->create(['type' => PhaseType::Groups, 'qualifiers_per_group' => 2, 'rules' => ['group_count' => 25, 'expected_entrants' => 100, 'vote_mode' => 'jury']]);

    $this->actingAs($owner, 'web')->post(route('organizers.competitions.phases.start', [$organizer, $competition, $phase]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Phase démarrée : format adapté aux 16 artistes, 4 poule(s) et 2 qualifié(s) par poule.');

    $phase->refresh();
    expect($phase->rules->groupCount)->toBe(4)
        ->and($phase->rules->expectedEntrants)->toBe(16)
        ->and($phase->groups()->count())->toBe(4);
});

it('still refuses to launch groups with fewer than 2 artists', function () {
    $competition = Competition::factory()->create(['status' => CompetitionStatus::Registration]);
    Participant::factory()->for($competition)->create(['status' => ParticipantStatus::Validated]);
    $phase = Phase::factory()->for($competition)->create(['type' => PhaseType::Groups, 'qualifiers_per_group' => 1, 'rules' => ['group_count' => 2, 'vote_mode' => 'jury']]);

    expect(fn () => app(PhaseLauncher::class)->start($phase))->toThrow(CompetitionFlowException::class, 'Il faut au moins 2 participants');
});
