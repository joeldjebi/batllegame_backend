<?php

use App\Models\Country;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const array TABLES = ['users', 'organizers', 'competitions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('seed_kind', 20)->nullable()->index();
            });
        }

        // The seeders never run in production: nothing to tag there.
        if (! app()->isProduction()) {
            $this->tagExistingSeedData();
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['seed_kind']);
                $table->dropColumn('seed_kind');
            });
        }
    }

    /**
     * Data seeded before this column existed (same identifiers as the seeders).
     */
    private function tagExistingSeedData(): void
    {
        $organizerId = DB::table('organizers')->where('slug', 'organisateur-test')->value('id');
        $country = Country::query()->where('iso2', 'CI')->first();

        if ($organizerId === null || $country === null) {
            return;
        }

        DB::table('organizers')->where('id', $organizerId)->update(['seed_kind' => 'compte_test']);
        DB::table('competitions')->where('organizer_id', $organizerId)
            ->whereIn('name', ['Abidjan Rap Battle 2026', "Voix d'Or de Cocody", 'Freestyle Session Yopougon', 'Abidjan Talents en ligne'])
            ->update(['seed_kind' => 'demo']);

        $phones = collect()
            ->merge(collect(range(0, 11))->map(fn (int $i) => '07'.(10000000 + $i)))
            ->merge(collect(range(0, 1))->map(fn (int $i) => '05'.(20000000 + $i)))
            ->merge(collect(range(0, 3))->map(fn (int $i) => '01'.(30000000 + $i)))
            ->map(fn (string $phone) => $country->toE164($phone));

        DB::table('users')->whereIn('phone', $phones)->update(['seed_kind' => 'demo']);
        // Generated fans: « Fan N », random +22501 number, no email (see DemoCompetitionSeeder::simulate()).
        DB::table('users')->whereNull('email')->where('phone', 'like', '+22501%')->get(['id', 'name'])
            ->filter(fn ($user) => preg_match('/^Fan \d+$/', $user->name))
            ->chunk(500)
            ->each(fn ($users) => DB::table('users')->whereIn('id', $users->pluck('id'))->update(['seed_kind' => 'demo']));

        $email = env('SEED_ORGANIZER_EMAIL');
        if ($email) {
            DB::table('users')->where('email', Str::lower($email))->update(['seed_kind' => 'compte_test']);
        }
        foreach (['SEED_JUDGE', 'SEED_ARTIST', 'SEED_FAN'] as $prefix) {
            if ($phone = env("{$prefix}_PHONE")) {
                DB::table('users')->where('phone', $country->toE164($phone))->update(['seed_kind' => 'compte_test']);
            }
        }
    }
};
