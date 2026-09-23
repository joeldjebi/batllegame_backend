<?php

namespace Database\Seeders;

use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Enums\PlatformRole;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local accounts: the platform super-admin and a verified test organizer,
 * both logging in to the web areas with email + password.
 * Credentials come from .env (SEED_*), never from the repository.
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

        $organizer = Organizer::query()->firstOrNew(['slug' => 'organisateur-test']);
        $organizer->fill(['name' => 'Organisateur Test', 'city' => 'Abidjan', 'description' => 'Organisateur de démonstration.']);
        $organizer->forceFill(['status' => OrganizerStatus::Verified, 'verified_at' => $organizer->verified_at ?? now()])->save();

        $organizer->users()->syncWithoutDetaching([$owner->id => ['role' => OrganizerRole::Owner]]);
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
