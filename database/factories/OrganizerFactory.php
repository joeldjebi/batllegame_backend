<?php

namespace Database\Factories;

use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organizer>
 */
class OrganizerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'status' => OrganizerStatus::Verified,
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrganizerStatus::Pending, 'verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => OrganizerStatus::Suspended]);
    }

    /**
     * Attach a member with the given role (creates the user when none is given).
     */
    public function withMember(OrganizerRole $role = OrganizerRole::Owner, ?User $user = null): static
    {
        return $this->afterCreating(function (Organizer $organizer) use ($role, $user): void {
            $organizer->users()->attach($user ?? User::factory()->create(), ['role' => $role]);
        });
    }
}
