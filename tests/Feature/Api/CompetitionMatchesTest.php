<?php

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\PhaseType;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
});

it('lists the matches of a phase for the groups and bracket views', function () {
    ['competition' => $competition, 'phase' => $phase] = startedCompetition(CompetitionMode::Online, 8, PhaseType::Groups, ['group_count' => 2], qualifiers: 2);

    $response = $this->getJson("/api/competitions/{$competition->slug}/matches")->assertOk();

    expect($response->json('phase.id'))->toBe($phase->id)
        ->and($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0'))->toMatchArray(['is_group' => true, 'group' => 'Poule A'])
        ->and($response->json('data.0.slots'))->toHaveCount(4)
        ->and($response->json('data.0.slots.0'))->toHaveKeys(['participant_id', 'stage_name', 'avatar_url', 'final_score', 'rank'])
        ->and($response->json('data.0.slots.0.final_score'))->toBeNull();

    $this->getJson("/api/competitions/{$competition->slug}/matches?phase={$phase->id}")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/competitions/{$competition->slug}/matches?phase=999999")->assertNotFound();
});

it('hides the matches of a draft competition', function () {
    ['competition' => $competition] = startedCompetition(CompetitionMode::Online, 2);
    $competition->update(['status' => CompetitionStatus::Draft]);

    $this->getJson("/api/competitions/{$competition->slug}/matches")->assertNotFound();
});
