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

it('refuses to launch groups the real participants cannot fill', function () {
    $competition = Competition::factory()->create(['status' => CompetitionStatus::Registration]);
    Participant::factory()->for($competition)->count(6)->create(['status' => ParticipantStatus::Validated]);
    $phase = Phase::factory()->for($competition)->create(['type' => PhaseType::Groups, 'qualifiers_per_group' => 2, 'rules' => ['group_count' => 3, 'vote_mode' => 'jury']]);

    expect(fn () => app(PhaseLauncher::class)->start($phase))->toThrow(CompetitionFlowException::class, 'Trop de qualifiés : la plus petite poule compte 2 artistes');
});
