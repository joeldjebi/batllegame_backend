<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizer competition list: sorted by recency, name or registration end within an organizer.
 * (Filters by status use competitions(organizer_id, status); name search uses the trigram index.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->index(['organizer_id', 'created_at']);
            $table->index(['organizer_id', 'name']);
            $table->index(['organizer_id', 'registration_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropIndex(['organizer_id', 'created_at']);
            $table->dropIndex(['organizer_id', 'name']);
            $table->dropIndex(['organizer_id', 'registration_ends_at']);
        });
    }
};
