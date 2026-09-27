<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Results (jury notes, scores, ranking) of a stage are public once the organizer
 * publishes them; group stages are published with their phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table): void {
            $table->timestamp('results_published_at')->nullable()->after('forfeits_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table): void {
            $table->dropColumn('results_published_at');
        });
    }
};
