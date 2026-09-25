<?php

use App\Enums\JudgeStatus;
use App\Models\Criterion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

/**
 * A pre-selection with $count entries, two criteria and one accepted judge.
 *
 * @return array<string, mixed>
 */
function juryPreselection(int $count = 3): array
{
    $setup = competitionWithPreselection($count);
    $setup['criteria'] = collect([Criterion::factory()->for($setup['competition'])->create(['name' => 'Flow', 'max_points' => 10]), Criterion::factory()->for($setup['competition'])->create(['name' => 'Texte', 'max_points' => 10])]);
    $setup['user'] = User::factory()->create();
    $setup['judge'] = $setup['competition']->judges()->create(['user_id' => $setup['user']->id, 'status' => JudgeStatus::Accepted]);
    $setup['entries'] = $setup['artists']->map(fn ($artist) => preselectionEntry($artist));

    return $setup;
}

function scoreEntry(array $setup, $entry, array $values = [7, 8])
{
    return test()->actingAs($setup['user'], 'sanctum')->postJson("/api/competitions/{$setup['competition']->slug}/preselection/entries/{$entry->id}/scores", [
        'scores' => $setup['criteria']->values()->map(fn ($criterion, $i) => ['criterion_id' => $criterion->id, 'score' => $values[$i]])->all(),
    ]);
}

it('lists the entries to score and the scored ones, with the progress and the next entry', function () {
    $setup = juryPreselection(3);
    [$first, $second] = $setup['entries'];
    $slug = $setup['competition']->slug;

    $list = $this->actingAs($setup['user'], 'sanctum')->getJson("/api/judge/competitions/{$slug}/preselection")->assertOk();
    expect($list->json('data.*.id'))->toBe($setup['entries']->pluck('id')->all())
        ->and($list->json('meta'))->toMatchArray(['total' => 3, 'scored' => 0, 'accepts_scores' => true, 'next_entry_id' => $first->id])
        ->and($list->json('meta.criteria.*.name'))->toBe(['Flow', 'Texte']);

    scoreEntry($setup, $first)->assertCreated()->assertJsonPath('next_entry_id', $second->id);

    $this->getJson("/api/judge/competitions/{$slug}/preselection")->assertJsonCount(2, 'data')->assertJsonPath('meta.scored', 1);
    $this->getJson("/api/judge/competitions/{$slug}/preselection?tab=notees")->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.0.my_total', 15);

    $this->getJson('/api/judge/competitions')->assertOk()->assertJsonPath('data.0.preselection.scored', 1)->assertJsonPath('data.0.preselection.total', 3);
});

it('shows one entry with the notes given, read-only once scored', function () {
    $setup = juryPreselection(2);
    [$first, $second] = $setup['entries'];
    $url = "/api/judge/competitions/{$setup['competition']->slug}/preselection/entries/{$first->id}";

    $this->actingAs($setup['user'], 'sanctum')->getJson($url)->assertOk()
        ->assertJsonPath('data.can_score', true)->assertJsonPath('data.my_scores', [])->assertJsonPath('data.next_entry_id', $second->id);

    scoreEntry($setup, $first);
    $this->getJson($url)->assertJsonPath('data.can_score', false)->assertJsonCount(2, 'data.my_scores');

    // Final notes.
    scoreEntry($setup, $first, [1, 1])->assertStatus(422);
});

it('refuses a user who is not an accepted judge of the competition', function () {
    $setup = juryPreselection(1);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')->getJson("/api/judge/competitions/{$setup['competition']->slug}/preselection")->assertNotFound();
    $this->actingAs($stranger, 'sanctum')->getJson("/api/judge/competitions/{$setup['competition']->slug}/preselection/entries/{$setup['entries'][0]->id}")->assertNotFound();

    // An entry of another competition is never reachable through this one.
    $other = juryPreselection(1);
    $this->actingAs($setup['user'], 'sanctum')->getJson("/api/judge/competitions/{$setup['competition']->slug}/preselection/entries/{$other['entries'][0]->id}")->assertNotFound();
});

it('only shows the entries assigned to the judge when the judging is split', function () {
    $setup = juryPreselection(4);
    $setup['competition']->preselection->update(['judges_per_entry' => 1]);
    $setup['competition']->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]);

    $this->actingAs($setup['user'], 'sanctum')->getJson("/api/judge/competitions/{$setup['competition']->slug}/preselection")->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.splits_judging', true);
});
