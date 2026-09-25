<?php

use App\Http\Controllers\Portal\Fan\PreselectionController;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('always shows an artist the likes of their own entry, not the others', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    [$mine, $other] = [preselectionEntry($artists[0]), preselectionEntry($artists[1])];
    likeAs($mine);
    likeAs($mine);
    likeAs($other);
    $artist = $artists[0]->user;

    expect(PreselectionController::likesState($competition->preselection, $artist)['counts'])->toBe([$mine->id => 2])
        ->and(PreselectionController::likesState($competition->preselection, null)['counts'])->toBeNull();

    $this->actingAs($artist, 'member')->get(route('artist.dashboard'))->assertOk()->assertSeeInOrder(['Ma prestation de présélection', '2']);

    $entries = collect($this->actingAs($artist, 'sanctum')->getJson("/api/competitions/{$competition->slug}/preselection/entries")->assertOk()->json('data'))->keyBy('id');
    expect($entries[$mine->id]['likes'])->toBe(2)
        ->and($entries[$other->id]['likes'])->toBeNull();
});
