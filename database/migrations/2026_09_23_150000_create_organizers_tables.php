<?php

use App\Enums\OrganizerPlan;
use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enum columns are stored as strings (not native PostgreSQL enums) so that
 * adding a case only requires a PHP change; values are guarded by enum casts
 * and request validation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('city')->nullable();
            $table->string('status', 20)->default(OrganizerStatus::Pending->value)->index();
            $table->timestamp('verified_at')->nullable();
            $table->string('plan', 20)->default(OrganizerPlan::Free->value);
            $table->timestamps();
        });

        Schema::create('organizer_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default(OrganizerRole::Staff->value);
            $table->timestamps();

            $table->unique(['organizer_id', 'user_id']);
            // Lookup of "which organizers does this user belong to".
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_members');
        Schema::dropIfExists('organizers');
    }
};
