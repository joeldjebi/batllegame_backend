<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No opening date any more: an artist sends the performance as soon as the registration
 * is validated (and paid); the key date is the submission deadline (ends_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preselections', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->change();
        });

        DB::table('preselections')->update(['starts_at' => null]);
    }

    public function down(): void
    {
        DB::table('preselections')->whereNull('starts_at')->update(['starts_at' => DB::raw('created_at')]);
    }
};
