<?php

namespace Database\Factories;

use App\Enums\PlatformRole;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'country_id' => fn () => Country::factory()->ivoryCoast()->getKey(),
            'phone' => '+22507'.fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's phone number should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * With a profile photo (required to submit a performance).
     */
    public function withAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar_disk' => 'public',
            'avatar_path' => 'avatars/'.Str::uuid().'.jpg',
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(Role::findOrCreate(PlatformRole::Admin->value, PlatformRole::GUARD));
        });
    }
}
