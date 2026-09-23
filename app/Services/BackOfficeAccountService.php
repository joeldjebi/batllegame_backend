<?php

namespace App\Services;

use App\Models\Country;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Back-office accounts (organizer owners and members) created by someone else:
 * an existing account (same email, or same phone without email) is reused,
 * otherwise a new one gets a temporary password (SMS + shown once) that must be
 * changed at first login.
 */
class BackOfficeAccountService
{
    public function __construct(private SmsSender $sms) {}

    /**
     * @return array{0: User, 1: ?string} The account and its temporary password when just created.
     */
    public function findOrCreate(string $email, ?string $name, ?Country $country, ?string $nationalPhone, string $context): array
    {
        $email = Str::lower(trim($email));

        if ($user = User::query()->where('email', $email)->first()) {
            return [$user, null];
        }

        if ($country === null || blank($nationalPhone) || blank($name)) {
            throw ValidationException::withMessages(['name' => "Aucun compte n'existe avec cet email : renseignez le nom et le téléphone pour le créer."]);
        }

        $phone = $country->toE164($nationalPhone);

        if ($user = User::query()->where('phone', $phone)->first()) {
            if ($user->email !== null) {
                throw ValidationException::withMessages(['phone' => 'Ce numéro appartient déjà à un autre compte back-office.']);
            }

            // A mobile account becomes a back-office account too: it keeps its password.
            $user->forceFill(['email' => $email])->save();

            return [$user, null];
        }

        $password = Str::password(10, symbols: false);
        $user = User::create(['name' => $name, 'email' => $email, 'country_id' => $country->id, 'phone' => $phone, 'password' => $password]);
        $user->forceFill(['must_change_password' => true, 'phone_verified_at' => now()])->save();

        $this->sms->send($phone, "Battle Game : un compte back-office a été créé pour vous ({$context}). Connectez-vous avec {$email} et le mot de passe provisoire {$password}, à changer à la première connexion.");

        return [$user, $password];
    }
}
