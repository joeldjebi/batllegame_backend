<?php

namespace Database\Seeders;

use App\Enums\CompetitionStatus;
use App\Enums\JudgeStatus;
use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PlatformRole;
use App\Enums\SeedKind;
use App\Models\City;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local accounts, credentials from .env (SEED_*), never from the repository:
 *  - super-admin and test organizer (email + password, web areas);
 *  - test judge, artist and fan (phone + password, portals /jury, /artiste, /vote).
 * Run again after DemoCompetitionSeeder to attach the judge and the artist to
 * the demo competitions.
 */
class LocalAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LocalAccountsSeeder must not run in production.');
        }

        $country = Country::query()->where('iso2', 'CI')->firstOrFail();

        $admin = $this->account($country, 'SEED_ADMIN', 'Super Admin');
        $admin->syncRoles([PlatformRole::Admin->value]);

        $owner = $this->account($country, 'SEED_ORGANIZER', 'Organisateur Test');
        $owner->forceFill(['seed_kind' => SeedKind::TestAccount])->save();

        $organizer = Organizer::query()->firstOrNew(['slug' => 'organisateur-test']);
        $abidjan = City::query()->where('country_id', $country->id)->where('name', 'Abidjan')->first();
        $organizer->fill(['name' => 'Organisateur Test', 'city_id' => $abidjan?->id, 'commune_id' => $abidjan?->communes()->where('name', 'Cocody')->value('id'), 'description' => 'Organisateur de démonstration.']);
        $organizer->forceFill(['status' => OrganizerStatus::Verified, 'verified_at' => $organizer->verified_at ?? now(), 'seed_kind' => SeedKind::TestAccount])->save();

        $organizer->users()->syncWithoutDetaching([$owner->id => ['role' => OrganizerRole::Owner]]);

        $this->portalAccounts($country, $organizer);
    }

    /**
     * Optional phone accounts for the web portals (skipped when not configured).
     */
    private function portalAccounts(Country $country, Organizer $organizer): void
    {
        if ($judge = $this->phoneAccount($country, 'SEED_JUDGE', 'Juré Test')) {
            // Judge of every competition of the test organizer (never where they compete).
            $organizer->competitions()->whereDoesntHave('participants', fn ($q) => $q->where('user_id', $judge->id))->get()
                ->each(fn ($competition) => $competition->judges()->firstOrCreate(['user_id' => $judge->id], ['status' => JudgeStatus::Accepted]));
        }

        if ($artist = $this->phoneAccount($country, 'SEED_ARTIST', 'Artiste Test')) {
            // Registered to the first competition open for registrations.
            $open = $organizer->competitions()->where('status', CompetitionStatus::Registration)->whereDoesntHave('judges', fn ($q) => $q->where('user_id', $artist->id))->first();
            $open?->participants()->firstOrCreate(['user_id' => $artist->id], ['stage_name' => 'Artiste Test', 'status' => ParticipantStatus::Validated]);
        }

        $this->phoneAccount($country, 'SEED_FAN', 'Public Test');
    }

    private function phoneAccount(Country $country, string $prefix, string $name): ?User
    {
        $phone = env("{$prefix}_PHONE");
        $password = env("{$prefix}_PASSWORD");

        if (! $phone || ! $password) {
            return null;
        }

        $user = User::query()->firstOrNew(['phone' => $country->toE164($phone)]);
        $user->fill(['name' => $user->name ?? $name, 'country_id' => $country->id, 'password' => $password]);
        // Ready to use: phone verified (voting), no forced password change.
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'must_change_password' => false, 'seed_kind' => SeedKind::TestAccount])->save();

        return $user;
    }

    private function account(Country $country, string $prefix, string $name): User
    {
        $email = env("{$prefix}_EMAIL");
        $phone = env("{$prefix}_PHONE");
        $password = env("{$prefix}_PASSWORD");

        if (! $email || ! $phone || ! $password) {
            throw new RuntimeException("Set {$prefix}_EMAIL, {$prefix}_PHONE and {$prefix}_PASSWORD in .env.");
        }

        // Back-office accounts log in with their email; the phone stays on the account.
        $user = User::query()->firstOrNew(['email' => Str::lower($email)]);
        $user->fill([
            'name' => $user->name ?? $name,
            'country_id' => $country->id,
            'phone' => $country->toE164($phone),
            'password' => $password,
        ]);
        $user->phone_verified_at ??= now();
        $user->save();

        return $user;
    }
}
