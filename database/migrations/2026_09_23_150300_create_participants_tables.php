<?php

use App\Enums\ParticipantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            // Restrict: an artist account with competition history must not vanish silently.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('stage_name');
            $table->unsignedSmallInteger('seed')->nullable();
            $table->string('status', 20)->default(ParticipantStatus::Registered->value);
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
            $table->index(['competition_id', 'status']);
            // "My competitions" screen of the mobile app.
            $table->index('user_id');
        });

        // Denormalized standings, always recomputable from closed group matches.
        Schema::create('group_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->integer('points')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('losses')->default(0);
            $table->decimal('score_diff', 8, 2)->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'participant_id']);
            $table->index('participant_id');
            $table->index(['group_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_participants');
        Schema::dropIfExists('participants');
    }
};
