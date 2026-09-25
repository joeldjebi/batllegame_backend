<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-ready media: a poster image, the display size of the video, and when
 * the file was optimized for streaming (moov atom first, compatible codecs).
 */
return new class extends Migration
{
    private const array TABLES = ['performances', 'preselection_submissions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('poster_path')->nullable()->after('media_disk');
                $table->unsignedSmallInteger('width')->nullable()->after('poster_path');
                $table->unsignedSmallInteger('height')->nullable()->after('width');
                $table->timestamp('optimized_at')->nullable()->after('height');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['poster_path', 'width', 'height', 'optimized_at']);
            });
        }
    }
};
