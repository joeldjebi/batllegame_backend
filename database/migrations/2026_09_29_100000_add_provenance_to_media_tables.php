<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance of uploaded media, read from their hidden metadata (App\Services\Media\MediaProvenance):
 * recording date, probable origin, summary of the tags, and the file date reported by the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['performances', 'preselection_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('recorded_at')->nullable();
                $table->string('media_origin', 20)->nullable();
                $table->jsonb('media_metadata')->nullable();
                // File date on the artist's device (File.lastModified): indicative only.
                $table->timestamp('client_modified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['performances', 'preselection_submissions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['recorded_at', 'media_origin', 'media_metadata', 'client_modified_at']));
        }
    }
};
