<?php

use App\Enums\CompetitionStatus;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('searches the competitions, the artists and their videos, case-insensitively', function () {
    ['artists' => $artists, 'competition' => $rap] = competitionWithPreselection(2);
    $rap->update(['name' => 'Abidjan Rap Battle']);
    $artists[0]->update(['stage_name' => 'Didi Flow']);
    $artists[1]->update(['stage_name' => 'Maestro K']);
    $didi = preselectionEntry($artists[0]->fresh());
    preselectionEntry($artists[1]->fresh());
    ['competition' => $danse] = competitionWithPreselection(1);
    $danse->update(['name' => 'Danse Urbaine Yopougon']);
    $danse->organizer->update(['name' => 'Yop Street Culture']);
    ['competition' => $draft] = competitionWithPreselection(1);
    $draft->update(['name' => 'Rap brouillon', 'status' => CompetitionStatus::Draft]);

    $this->getJson('/api/search?q=rap')->assertOk()
        ->assertJsonCount(1, 'competitions')
        ->assertJsonPath('competitions.0.slug', $rap->slug);
    $this->getJson('/api/search?q=street')->assertJsonPath('competitions.0.slug', $danse->slug);

    $this->getJson('/api/search?q=DIDI')->assertOk()
        ->assertJsonCount(1, 'artists')
        ->assertJsonPath('artists.0.stage_name', 'Didi Flow')
        ->assertJsonPath('artists.0.competition.slug', $rap->slug);

    // Videos: the artist's name or the competition's.
    $this->getJson('/api/feed?q=didi')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $didi->id);
    $this->getJson('/api/feed?q=abidjan')->assertJsonCount(2, 'data');
    $this->getJson('/api/feed?q=100%')->assertJsonCount(0, 'data');
    $this->getJson('/api/competitions?q=yop')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $danse->slug);
});

it('suggests the running competitions and the artists of the latest videos when empty', function () {
    ['artists' => $artists, 'competition' => $competition] = competitionWithPreselection(1);
    preselectionEntry($artists[0]);

    $this->getJson('/api/search')->assertOk()
        ->assertJsonPath('competitions.0.slug', $competition->slug)
        ->assertJsonPath('artists.0.participant_id', $artists[0]->id);
});
