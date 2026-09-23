<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performances become per-stage submissions (one media per participant and
     * stage, reused in all the matches of the stage) or on-site captations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropUnique(['match_id', 'participant_id', 'turn']);
        });

        Schema::table('performances', function (Blueprint $table) {
            // Denormalized from the stage for scoping.
            $table->foreignId('competition_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->after('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->change();
            $table->string('media_disk', 30)->nullable()->after('media_path');
            $table->string('media_type', 10)->nullable()->after('media_disk');
            $table->string('mime_type', 100)->nullable()->after('media_type');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->unsignedInteger('duration_seconds')->nullable()->after('size_bytes');
            $table->string('original_name')->nullable()->after('duration_seconds');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('rejection_reason')->nullable()->after('reviewed_at');

            $table->unique(['stage_id', 'participant_id', 'turn']);
            $table->index(['competition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropUnique(['stage_id', 'participant_id', 'turn']);
            $table->dropIndex(['competition_id', 'status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('stage_id');
            $table->dropConstrainedForeignId('competition_id');
            $table->dropColumn(['media_disk', 'media_type', 'mime_type', 'size_bytes', 'duration_seconds', 'original_name', 'reviewed_at', 'rejection_reason']);
            $table->unique(['match_id', 'participant_id', 'turn']);
        });
    }
};
