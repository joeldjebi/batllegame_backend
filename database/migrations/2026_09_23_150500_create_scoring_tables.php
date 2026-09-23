<?php

use App\Enums\JudgeStatus;
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
        Schema::create('judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default(JudgeStatus::Invited->value);
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('max_points');
            $table->decimal('weight', 5, 2)->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['competition_id', 'position']);
        });

        Schema::create('jury_scores', function (Blueprint $table) {
            $table->id();
            // Denormalized from match for scoping.
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            // Restrict: a judge or criterion that has been used cannot be deleted.
            $table->foreignId('judge_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'judge_id', 'participant_id', 'criterion_id']);
            $table->index(['match_id', 'participant_id']);
            $table->index('competition_id');
        });

        Schema::create('public_votes', function (Blueprint $table) {
            $table->id();
            // Denormalized from match for scoping.
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
            // Vote counting per participant.
            $table->index(['match_id', 'participant_id']);
            // Fraud detection: several accounts voting from the same device.
            $table->index(['match_id', 'device_id']);
            $table->index('competition_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_votes');
        Schema::dropIfExists('jury_scores');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('judges');
    }
};
