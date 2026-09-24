<?php

use App\Enums\JudgeStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\Criterion;
use App\Models\PreselectionScore;
use App\Models\User;
use App\Services\JuryWorkload;
use App\Services\PreselectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

function judges(object $competition, int $count): array
{
    return collect(range(1, $count))->map(fn () => $competition->judges()->create(['user_id' => User::factory()->create()->id, 'status' => JudgeStatus::Accepted]))->all();
}

it('makes the notes final, and lets the organizer reopen them', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(1);
    $criterion = Criterion::factory()->for($competition)->create(['max_points' => 10]);
    [$judge] = judges($competition, 1);
    $entry = preselectionEntry($artists[0]);
    $score = fn (int $value) => app(PreselectionService::class)->score($judge, $entry, ['scores' => [['criterion_id' => $criterion->id, 'score' => $value]]]);

    $score(8);
    expect(fn () => $score(3))->toThrow(CompetitionFlowException::class, 'définitives');

    $this->actingAs($owner, 'web')->deleteJson(route('organizers.competitions.preselection.entries.scores.destroy', [$organizer, $competition, $entry, $judge]))->assertOk();
    $score(3);

    expect(PreselectionScore::query()->where('judge_id', $judge->id)->value('score'))->toEqual(3.0)
        ->and($entry->fresh()->jury_score)->toBe(30.0);
});

it('lets every judge score every entry by default', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(3);
    [$a, $b] = judges($competition, 2);
    $artists->each(fn ($artist) => preselectionEntry($artist));

    expect(app(JuryWorkload::class)->entriesFor($a, $competition->preselection)->count())->toBe(3)
        ->and(app(JuryWorkload::class)->entriesFor($b, $competition->preselection)->count())->toBe(3);
});

it('splits the entries between the judges, balanced, N judges per entry', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(9);
    $competition->preselection->update(['judges_per_entry' => 2]);
    $judges = judges($competition, 3);
    $artists->each(fn ($artist) => preselectionEntry($artist));
    $workload = app(JuryWorkload::class);

    $workload->sync($competition->preselection);
    $workload->sync($competition->preselection); // idempotent

    $perEntry = DB::table('preselection_assignments')->selectRaw('submission_id, count(*) as n')->groupBy('submission_id')->pluck('n')->unique()->values()->all();
    $perJudge = collect($judges)->map(fn ($judge) => $workload->entriesFor($judge, $competition->preselection)->count())->all();

    expect($perEntry)->toBe([2])->and($perJudge)->toBe([6, 6, 6]);

    // A judge cannot score an entry that is not on their list.
    $criterion = Criterion::factory()->for($competition)->create();
    $foreign = $competition->preselection->entries()->whereNotIn('id', $workload->entriesFor($judges[0], $competition->preselection)->select('id'))->first();
    expect(fn () => app(PreselectionService::class)->score($judges[0], $foreign, ['scores' => [['criterion_id' => $criterion->id, 'score' => 5]]]))
        ->toThrow(CompetitionFlowException::class, "n'est pas attribuée");
});

it('shows a judge a paginated list, then one entry at a time, and moves on after scoring', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(25);
    $criterion = Criterion::factory()->for($competition)->create(['name' => 'Flow', 'max_points' => 10]);
    [$judge] = judges($competition, 1);
    $entries = $artists->map(fn ($artist) => preselectionEntry($artist));
    $list = route('jury.competitions.preselection', $competition);

    $this->actingAs($judge->user, 'jury')->get($list)->assertOk()->assertSee('0 <span class="text-lg font-bold text-slate-400">/ 25 notées</span>', false)->assertSee('?page=2', false);

    $this->actingAs($judge->user, 'jury')->post(route('jury.competitions.preselection.scores.store', [$competition, $entries[0]]), ['scores' => [['criterion_id' => $criterion->id, 'score' => 7]]])
        ->assertRedirect(route('jury.competitions.preselection.entries.show', [$competition, $entries[1]]));

    $this->actingAs($judge->user, 'jury')->get($list.'?onglet=notees')->assertOk()->assertSee($entries[0]->participant->stage_name)->assertSee('7 pts');
    $this->actingAs($judge->user, 'jury')->get(route('jury.competitions.preselection.entries.show', [$competition, $entries[0]]))->assertOk()->assertSee('Tes notes (définitives)');
});

it('pages the organizer ranking on the server and shows the jury progress', function () {
    ['competition' => $competition, 'artists' => $artists, 'owner' => $owner, 'organizer' => $organizer] = competitionWithPreselection(23);
    judges($competition, 2);
    $artists->each(fn ($artist) => preselectionEntry($artist));
    $show = route('organizers.competitions.show', [$organizer, $competition]);

    $page1 = $this->actingAs($owner, 'web')->get($show)->assertOk()->assertSee('Avancement du jury')->assertSee('0 / 23')->getContent();
    expect($page1)->toContain('pre_page=2');

    $this->actingAs($owner, 'web')->get($show.'?pre_page=2')->assertOk()->assertSee($artists->sortBy('id')->last()->stage_name);
});
