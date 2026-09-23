<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the listings, dashboards and searches (filters + sort columns),
 * and trigram indexes for "contains" searches on PostgreSQL.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<list<string>>>
     */
    private array $indexes = [
        // Public lists, admin lists and dashboards sort by recency within a status.
        'competitions' => [['status', 'created_at'], ['created_at']],
        'organizers' => [['status', 'name'], ['created_at']],
        'organizer_members' => [['organizer_id', 'role']],
        'users' => [['created_at'], ['phone_verified_at']],
        'judges' => [['user_id', 'status'], ['competition_id', 'status']],
        'participants' => [['competition_id', 'seed'], ['user_id', 'created_at']],
        'stages' => [['phase_id', 'status']],
        // Stage panels, jury and public pages: matches of a stage / latest activity of a competition.
        'matches' => [['stage_id', 'status'], ['competition_id', 'updated_at']],
        'performances' => [['stage_id', 'status'], ['match_id', 'participant_id']],
        'jury_scores' => [['judge_id', 'match_id']],
        // Votes of the last 24 h (platform, competition) and a voter's history.
        'public_votes' => [['created_at'], ['competition_id', 'created_at'], ['user_id', 'created_at']],
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $trigram = [
        'organizers' => ['name'],
        'competitions' => ['name'],
        'users' => ['name', 'email', 'phone'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }

        if (! $this->usesTrigram()) {
            return;
        }

        foreach ($this->trigram as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$table}_{$column}_trgm_index ON {$table} USING gin ({$column} gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->trigram as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm_index");
                }
            }
        }

        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }

    /**
     * pg_trgm speeds up ILIKE '%…%' searches; skipped when the extension cannot be enabled.
     */
    private function usesTrigram(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            return true;
        } catch (Throwable $e) {
            Log::warning('pg_trgm unavailable, trigram search indexes skipped: '.$e->getMessage());

            return false;
        }
    }
};
