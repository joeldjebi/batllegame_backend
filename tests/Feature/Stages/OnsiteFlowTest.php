<?php

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\OrganizerRole;
use App\Enums\PerformanceSource;
use App\Enums\PerformanceStatus;
use App\Enums\StageStatus;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
});

it('opens the vote of a match live, for a limited time', function () {
    ['phase' => $phase, 'organizer' => $organizer, 'competition' => $competition, 'owner' => $owner] = startedCompetition(CompetitionMode::OnSite);
    $match = $phase->matches()->where('round', 1)->first();

    $this->actingAs($owner)
        ->post(route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]), ['duration' => 5])
        ->assertSessionHasNoErrors();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::Voting)
        ->and($match->vote_code)->toBeNull()
        ->and((int) round(now()->diffInMinutes($match->voting_closes_at)))->toBe(5)
        ->and($match->stage->status)->toBe(StageStatus::Voting);
});

it('requires the room code when the competition restricts voting to people present', function () {
    ['phase' => $phase, 'organizer' => $organizer, 'competition' => $competition, 'owner' => $owner] = startedCompetition(CompetitionMode::OnSite, settings: ['onsite_vote_code' => true]);
    $match = $phase->matches()->where('round', 1)->first();

    $this->actingAs($owner)->post(route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]));
    $code = $match->fresh()->vote_code;
    $participantId = $match->slots()->value('participant_id');
    $url = "/api/competitions/{$competition->slug}/matches/{$match->id}/votes";

    expect($code)->toMatch('/^\d{4}$/');

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url, ['participant_id' => $participantId])->assertJsonValidationErrors('vote_code');
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url, ['participant_id' => $participantId, 'vote_code' => $code === '0000' ? '1111' : '0000'])->assertJsonValidationErrors('vote_code');
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson($url, ['participant_id' => $participantId, 'vote_code' => $code])->assertCreated();

    $this->getJson("/api/competitions/{$competition->slug}/matches/{$match->id}")->assertJsonPath('data.vote_code_required', true)->assertJsonMissing(['vote_code' => $code]);

    $this->actingAs($owner)->get(route('organizers.competitions.show', [$organizer, $competition]))->assertSee($code);
});

it('lets staff upload the captation of a live battle', function () {
    ['phase' => $phase, 'organizer' => $organizer, 'competition' => $competition] = startedCompetition(CompetitionMode::OnSite);
    $staff = User::factory()->create();
    $organizer->users()->attach($staff, ['role' => OrganizerRole::Staff]);
    $match = $phase->matches()->where('round', 1)->first();
    $participantId = $match->slots()->value('participant_id');

    $this->actingAs($staff)
        ->post(route('organizers.competitions.matches.captations.store', [$organizer, $competition, $match]), ['participant_id' => $participantId, 'media' => fakeVideo()])
        ->assertSessionHasNoErrors();

    $captation = Performance::query()->sole();

    expect($captation->source)->toBe(PerformanceSource::Capture)
        ->and($captation->status)->toBe(PerformanceStatus::Approved)
        ->and($captation->stage_id)->toBe($match->stage_id);

    $this->getJson("/api/competitions/{$competition->slug}/matches/{$match->id}")->assertJsonPath('data.slots.0.media.0.source', 'captation');
});

it('closes an on-site stage when all its matches are played', function () {
    ['phase' => $phase, 'organizer' => $organizer, 'competition' => $competition, 'owner' => $owner] = startedCompetition(CompetitionMode::OnSite, 2);
    $match = $phase->matches()->first();

    $this->actingAs($owner)->post(route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]));
    $this->actingAs($owner)->post(route('organizers.competitions.matches.close', [$organizer, $competition, $match]))->assertSessionHasNoErrors();

    expect($match->stage->fresh()->status)->toBe(StageStatus::Closed);
});
