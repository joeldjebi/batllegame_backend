<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover image of a competition (16:9), chosen by the organizer; the apps show it in
 * Découvrir (else the poster of one of its performances).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->string('cover_path')->nullable()->after('regulations');
            $table->string('cover_disk')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->dropColumn(['cover_path', 'cover_disk']);
        });
    }
};
