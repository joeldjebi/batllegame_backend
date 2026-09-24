<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference places managed by the super-admin (country > city > commune), picked
 * from lists instead of typed: organizers, competitions (venue) and users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            // Inactive: hidden from the lists, kept on existing records.
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['country_id', 'name']);
            $table->index(['country_id', 'is_active', 'position']);
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['city_id', 'name']);
            $table->index(['city_id', 'is_active', 'position']);
        });

        foreach (['organizers', 'competitions', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        $this->moveOrganizerCities();

        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->string('city', 100)->nullable();
        });

        DB::table('organizers')->whereNotNull('city_id')->update([
            'city' => DB::raw('(select name from cities where cities.id = organizers.city_id)'),
        ]);

        foreach (['organizers', 'competitions', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('commune_id');
                $table->dropConstrainedForeignId('city_id');
            });
        }

        Schema::dropIfExists('communes');
        Schema::dropIfExists('cities');
    }

    /**
     * Typed organizer cities become reference cities (Côte d'Ivoire), so nothing is lost.
     */
    private function moveOrganizerCities(): void
    {
        $typed = DB::table('organizers')->whereNotNull('city')->where('city', '!=', '')->get(['id', 'city']);
        $countryId = DB::table('countries')->where('iso2', 'CI')->value('id');

        if ($typed->isEmpty() || $countryId === null) {
            return;
        }

        foreach ($typed as $organizer) {
            $name = trim($organizer->city);
            $cityId = DB::table('cities')->where('country_id', $countryId)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('id')
                ?? DB::table('cities')->insertGetId(['country_id' => $countryId, 'name' => $name, 'is_active' => true, 'position' => 999, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('organizers')->where('id', $organizer->id)->update(['city_id' => $cityId]);
        }
    }
};
