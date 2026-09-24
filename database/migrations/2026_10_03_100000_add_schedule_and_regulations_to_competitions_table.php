<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Written by the organizer for artists and the public: the schedule (ordered steps
 * with an optional date) and the regulations (sanitized rich text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->jsonb('schedule')->nullable();
            $table->text('regulations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'regulations']);
        });
    }
};
