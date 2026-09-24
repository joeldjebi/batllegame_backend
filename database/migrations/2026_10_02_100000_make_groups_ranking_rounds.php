<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups become ranking rounds: the artists of a group do not face each other.
 * Each group is one match whose slots are all its members; each artist performs,
 * the public (one vote per phase) and the jury rank them, the organizer publishes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_participants', function (Blueprint $table) {
            // Online: no valid submission at the deadline (out of the ranking, cannot qualify).
            $table->boolean('is_forfeit')->default(false);
            // Rank in the group (ranking rounds only), set when the group is closed.
            $table->unsignedSmallInteger('rank')->nullable();
        });

        Schema::table('phases', function (Blueprint $table) {
            $table->timestamp('results_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->dropColumn('results_published_at');
        });

        Schema::table('match_participants', function (Blueprint $table) {
            $table->dropColumn(['is_forfeit', 'rank']);
        });
    }
};
