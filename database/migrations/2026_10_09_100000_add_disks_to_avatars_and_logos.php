<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disk of each profile photo and logo (null = « public », files stored before Wasabi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('avatar_disk', 20)->nullable());
        Schema::table('organizers', fn (Blueprint $table) => $table->string('logo_disk', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_disk'));
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn('logo_disk'));
    }
};
