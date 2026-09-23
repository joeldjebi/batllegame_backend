<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            // Sanitized rich text (HTML) written by the organizer.
            $table->text('description')->nullable()->after('slug');
            // Ordered list of rewards: [{"rank": "1er prix", "reward": "500 000 XOF"}].
            $table->jsonb('prizes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['description', 'prizes']);
        });
    }
};
