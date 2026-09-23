<?php

use App\Enums\MatchStatus;
use App\Enums\PerformanceStatus;
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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            // Denormalized from phase for scoping and fast filtering.
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            // winners / losers / grand_final for elimination phases, null for group matches.
            $table->string('bracket', 20)->nullable();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('bracket_position');
            $table->foreignId('next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->unsignedTinyInteger('next_match_slot')->nullable();
            // Double elimination: where the loser drops in the losers bracket.
            $table->foreignId('loser_next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->unsignedTinyInteger('loser_next_match_slot')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('submission_deadline')->nullable();
            $table->timestamp('voting_opens_at')->nullable();
            $table->timestamp('voting_closes_at')->nullable();
            $table->string('status', 20)->default(MatchStatus::Scheduled->value);
            // Null on a closed match means a draw (group phases only).
            $table->foreignId('winner_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['phase_id', 'bracket', 'round', 'bracket_position']);
            $table->index(['competition_id', 'status']);
            // Scheduler: find matches whose voting window has ended.
            $table->index(['status', 'voting_closes_at']);
            $table->index('group_id');
            $table->index('next_match_id');
            $table->index('loser_next_match_id');
        });

        Schema::create('match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            // Null while the slot awaits the winner of a previous match (or is a bye).
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('slot');
            // Denormalized scores (0-100), recomputable from jury_scores and public_votes.
            $table->decimal('jury_score', 5, 2)->nullable();
            $table->decimal('public_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'slot']);
            $table->index('participant_id');
        });

        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            // Passage number inside the battle (distinct from the bracket round of the match).
            $table->unsignedTinyInteger('turn')->default(1);
            $table->string('media_path')->nullable();
            $table->string('source', 20);
            $table->string('status', 20)->default(PerformanceStatus::Pending->value);
            $table->timestamps();

            $table->unique(['match_id', 'participant_id', 'turn']);
            $table->index('participant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performances');
        Schema::dropIfExists('match_participants');
        Schema::dropIfExists('matches');
    }
};
