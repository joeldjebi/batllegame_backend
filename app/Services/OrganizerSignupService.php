<?php

namespace App\Services;

use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Models\Country;
use App\Models\Organizer;
use App\Models\User;
use App\Realtime\Channel;
use App\Realtime\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organizers who open their own space. It starts « en attente »: they can prepare
 * competitions, the super-admin verifies it before registrations can open.
 */
class OrganizerSignupService
{
    public function __construct(private Realtime $realtime) {}

    /**
     * A visitor: creates the owner account, or reuses their mobile account (same phone, proven by its password).
     *
     * @param  array{name: string, email: string, password: string}  $account
     * @param  array{organizer_name: string, city_id?: ?int, commune_id?: ?int, description?: ?string}  $organizer
     * @return array{0: Organizer, 1: User}
     */
    public function register(array $account, Country $country, string $e164Phone, array $organizer): array
    {
        return DB::transaction(function () use ($account, $country, $e164Phone, $organizer): array {
            $owner = $this->owner($account, $country, $e164Phone);

            return [$this->create($owner, $organizer), $owner];
        });
    }

    /**
     * A signed-in back-office user opens another organizer.
     *
     * @param  array{organizer_name: string, city_id?: ?int, commune_id?: ?int, description?: ?string}  $data
     */
    public function create(User $owner, array $data): Organizer
    {
        if ($owner->isPlatformAdmin()) {
            throw ValidationException::withMessages(['email' => 'Le super-admin ne peut pas être propriétaire d\'un organisateur.']);
        }

        $organizer = DB::transaction(function () use ($owner, $data): Organizer {
            $organizer = new Organizer(['name' => trim($data['organizer_name']), 'city_id' => $data['city_id'] ?? null, 'commune_id' => $data['commune_id'] ?? null, 'description' => $data['description'] ?? null]);
            $organizer->slug = Organizer::uniqueSlug($organizer->name);
            $organizer->forceFill(['status' => OrganizerStatus::Pending])->save();
            $organizer->users()->attach($owner, ['role' => OrganizerRole::Owner]);

            return $organizer;
        });

        // Sent once the request ends (buffered), so only for an organizer really created.
        $this->realtime->push([Channel::ADMIN], 'organizer.registered', ['organizer_id' => $organizer->id],
            "Nouvel organisateur à vérifier : {$organizer->name}");

        return $organizer;
    }

    /**
     * @param  array{name: string, email: string, password: string}  $account
     */
    private function owner(array $account, Country $country, string $phone): User
    {
        $email = Str::lower(trim($account['email']));

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Un compte existe déjà avec cet email : connectez-vous pour créer votre organisateur.']);
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user !== null) {
            // A mobile account (artist, fan, judge) becomes a back-office account too, once its owner proves it.
            if ($user->email !== null || $user->isPlatformAdmin()) {
                throw ValidationException::withMessages(['phone' => 'Ce numéro appartient déjà à un compte back-office : connectez-vous avec son email.']);
            }

            if (! Hash::check($account['password'], $user->password)) {
                throw ValidationException::withMessages(['phone' => 'Ce numéro a déjà un compte Battle Game : saisissez son mot de passe pour le relier à votre espace organisateur.']);
            }

            $user->forceFill(['email' => $email])->save();

            return $user;
        }

        return User::create([
            'name' => trim($account['name']),
            'email' => $email,
            'country_id' => $country->id,
            'phone' => $phone,
            'password' => $account['password'],
        ]);
    }
}
