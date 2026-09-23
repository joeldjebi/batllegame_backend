<?php

use App\Enums\StageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stage groups the matches played at the same time within a phase: the
     * whole phase for groups, one bracket round for elimination. Participants
     * submit one media per stage, and a stage has its own schedule.
     */
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name');
            $table->string('status', 20)->default(StageStatus::Pending->value);
            $table->timestamp('submission_deadline')->nullable();
            $table->timestamp('voting_opens_at')->nullable();
            $table->timestamp('voting_closes_at')->nullable();
            // Set once missing submissions have been turned into forfeits.
            $table->timestamp('forfeits_applied_at')->nullable();
            $table->timestamps();

            $table->unique(['phase_id', 'number']);
            // Scheduler: stages whose submission deadline has passed.
            $table->index(['status', 'submission_deadline']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('group_id')->constrained()->cascadeOnDelete();
            // Decided without playing (missing submission).
            $table->boolean('is_forfeit')->default(false)->after('winner_id');
            // On-site competitions: code shown in the room, required to vote.
            $table->string('vote_code', 8)->nullable()->after('voting_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->dropColumn(['is_forfeit', 'vote_code']);
        });

        Schema::dropIfExists('stages');
    }
};
