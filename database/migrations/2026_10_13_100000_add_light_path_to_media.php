<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A light copy (480p) of each video, next to the HD file: the apps pick it on
 * mobile data so playback starts faster and uses less data.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['performances', 'preselection_submissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('light_path')->nullable()->after('poster_path');
            });
        }
    }

    public function down(): void
    {
        foreach (['performances', 'preselection_submissions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('light_path');
            });
        }
    }
};
