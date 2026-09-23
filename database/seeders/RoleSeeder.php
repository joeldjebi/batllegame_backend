<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed platform-wide roles. Organizer roles live in organizer_members, not here.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
