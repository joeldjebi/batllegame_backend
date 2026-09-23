<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizer-defined timeline: end of submissions → end of the public vote → end of the
 * jury deliberation (a duration after the vote).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preselections', function (Blueprint $table) {
            // Null = the vote ends with the submissions (ends_at).
            $table->timestamp('vote_ends_at')->nullable()->after('ends_at');
            $table->unsignedSmallInteger('deliberation_hours')->default(0)->after('vote_ends_at');
        });

        Schema::table('stages', function (Blueprint $table) {
            $table->unsignedSmallInteger('deliberation_minutes')->default(0)->after('voting_closes_at');
        });

        Schema::table('matches', function (Blueprint $table) {
            // Jury scores are accepted until then (public votes until voting_closes_at).
            $table->timestamp('deliberation_ends_at')->nullable()->after('voting_closes_at');
            $table->index(['status', 'deliberation_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['status', 'deliberation_ends_at']);
            $table->dropColumn('deliberation_ends_at');
        });
        Schema::table('stages', fn (Blueprint $table) => $table->dropColumn('deliberation_minutes'));
        Schema::table('preselections', fn (Blueprint $table) => $table->dropColumn(['vote_ends_at', 'deliberation_hours']));
    }
};
