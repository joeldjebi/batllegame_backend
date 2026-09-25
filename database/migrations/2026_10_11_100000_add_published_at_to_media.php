<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a media became public (status « validee »): the stable order of the mobile
 * feed (updated_at moves with every like). Kept by HasMediaFile on status changes.
 */
return new class extends Migration
{
    private const array TABLES = ['performances', 'preselection_submissions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->timestamp('published_at')->nullable()->after('optimized_at');
                $table->index(['status', 'published_at', 'id'], "{$name}_feed_index");
            });

            DB::table($name)->where('status', 'validee')->update(['published_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropIndex("{$name}_feed_index");
                $table->dropColumn('published_at');
            });
        }
    }
};
