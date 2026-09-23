<?php

use App\Enums\PaymentStatus;
use App\Enums\PerformanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registration fee payments (simulated for now) and the pre-selection
     * round: one submission per registered artist, public likes (one per user
     * and competition), jury scores, then the selection of N artists.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('method', 20);
            $table->string('provider', 20)->default('simulation');
            $table->string('reference', 30)->unique();
            $table->string('status', 20)->default(PaymentStatus::Pending->value);
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'status']);
            $table->index(['participant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('preselections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->jsonb('rules')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('preselection_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preselection_id')->constrained()->cascadeOnDelete();
            // Denormalized for scoping.
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('media_path')->nullable();
            $table->string('media_disk', 30)->nullable();
            $table->string('media_type', 10)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('original_name')->nullable();
            $table->string('status', 20)->default(PerformanceStatus::Processing->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            // Derived, recomputable from preselection_likes / preselection_scores.
            $table->unsignedInteger('likes_count')->default(0);
            $table->decimal('jury_score', 5, 2)->nullable();
            $table->decimal('like_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->boolean('selected')->nullable();
            $table->timestamps();

            $table->unique(['preselection_id', 'participant_id']);
            $table->index(['preselection_id', 'status']);
            $table->index(['preselection_id', 'rank']);
            $table->index('competition_id');
        });

        Schema::create('preselection_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preselection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('preselection_submissions')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('device_id', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // One like per user and competition during the pre-selection.
            $table->unique(['preselection_id', 'user_id']);
            $table->index('submission_id');
            $table->index(['preselection_id', 'device_id']);
        });

        Schema::create('preselection_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('preselection_submissions')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained()->restrictOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'judge_id', 'criterion_id']);
            $table->index(['judge_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preselection_scores');
        Schema::dropIfExists('preselection_likes');
        Schema::dropIfExists('preselection_submissions');
        Schema::dropIfExists('preselections');
        Schema::dropIfExists('payments');
    }
};
