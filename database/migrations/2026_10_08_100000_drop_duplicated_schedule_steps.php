<?php

use App\Models\Competition;
use App\Services\CompetitionGuideDraft;
use Illuminate\Database\Migrations\Migration;

/**
 * The schedule is now automatic: steps saved from the former draft that repeat an
 * automatic step (same title) are removed; the organizer's own steps are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $draft = app(CompetitionGuideDraft::class);

        Competition::query()->withTrashed()->whereNotNull('schedule')->each(function (Competition $competition) use ($draft): void {
            $automatic = collect($draft->automaticSchedule($competition))->pluck('title')
                // Former draft titles of a phase (« Phase 2 · Élimination simple »), now detailed by stage.
                ->merge($competition->phases->map(fn ($phase) => "Phase {$phase->position} · {$phase->type->label()}"))
                ->map(fn ($t) => mb_strtolower($t));
            $kept = collect($competition->scheduleList())->reject(fn ($step) => $automatic->contains(mb_strtolower($step['title'])))->values()->all();

            $competition->forceFill(['schedule' => $kept ?: null])->saveQuietly();
        });
    }

    public function down(): void {}
};
