<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planned calendar of a phase, set before it starts: dates per stage name
 * (« Poules », « Quarts de finale », « Finale »…), applied to the stages at launch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->jsonb('calendar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->dropColumn('calendar');
        });
    }
};
