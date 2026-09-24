<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional split of the pre-selection entries between the judges: null = every judge
 * scores every entry (default); N = each entry is scored by N judges, balanced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preselections', function (Blueprint $table) {
            $table->unsignedTinyInteger('judges_per_entry')->nullable();
        });

        Schema::create('preselection_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('preselection_submissions')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['submission_id', 'judge_id']);
            $table->index('judge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preselection_assignments');
        Schema::table('preselections', function (Blueprint $table) {
            $table->dropColumn('judges_per_entry');
        });
    }
};
