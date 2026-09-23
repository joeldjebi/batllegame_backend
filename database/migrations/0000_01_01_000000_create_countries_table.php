<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->unique();
            // International dialing code including the plus sign, e.g. "+225".
            $table->string('dial_code', 6);
            // Allowed length of the national number (without dial code).
            $table->unsignedTinyInteger('phone_min_length');
            $table->unsignedTinyInteger('phone_max_length');
            $table->string('phone_example', 20)->nullable();
            $table->string('flag', 8)->nullable();
            $table->char('currency_code', 3)->nullable();
            // Only active countries are offered in phone number selectors.
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
