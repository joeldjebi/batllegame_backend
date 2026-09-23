<?php

use App\Enums\CompetitionStatus;
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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            // The only place (with organizer_members) where organizer_id lives.
            $table->foreignId('organizer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            // Globally unique so the mobile app can address a competition by slug alone.
            $table->string('slug')->unique();
            $table->string('discipline', 20);
            $table->string('mode', 20);
            $table->string('status', 20)->default(CompetitionStatus::Draft->value);
            $table->timestamp('registration_ends_at')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            // Amount in the currency's minor unit (XOF has no decimals).
            $table->unsignedInteger('entry_fee')->default(0);
            $table->char('currency', 3)->default('XOF');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'status']);
            $table->index(['status', 'registration_ends_at']);
            $table->index('discipline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
