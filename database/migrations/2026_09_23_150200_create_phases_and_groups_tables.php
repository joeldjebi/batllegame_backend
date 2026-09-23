<?php

use App\Enums\PhaseStatus;
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
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('position');
            // Null means the phase inherits the competition mode.
            $table->string('mode', 20)->nullable();
            $table->unsignedTinyInteger('qualifiers_per_group')->nullable();
            $table->jsonb('rules')->nullable();
            $table->string('status', 20)->default(PhaseStatus::Pending->value);
            // Set when the phase starts; rules are frozen from then on.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'position']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['phase_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
        Schema::dropIfExists('phases');
    }
};
