<?php

namespace App\Services;

use App\Enums\JudgeStatus;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Judge;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organizers create their judges per competition. A new number gets an
 * account with a temporary password sent by SMS; an existing account is reused.
 */
class JudgeAccountService
{
    public function __construct(private SmsSender $sms) {}

    /**
     * @return array{0: Judge, 1: ?string} The judge and the temporary password when an account was created.
     */
    public function assign(Competition $competition, Country $country, string $nationalPhone, string $name): array
    {
        $phone = $country->toE164($nationalPhone);
        $user = User::query()->where('phone', $phone)->first();

        if ($user?->isParticipantOf($competition)) {
            throw ValidationException::withMessages(['phone' => 'Un participant ne peut pas être membre du jury de la même compétition.']);
        }

        if ($user?->isJudgeOf($competition, acceptedOnly: false)) {
            throw ValidationException::withMessages(['phone' => 'Cette personne fait déjà partie du jury.']);
        }

        $password = null;

        $judge = DB::transaction(function () use ($competition, $country, $phone, $name, &$user, &$password): Judge {
            if ($user === null) {
                $password = Str::password(10, symbols: false);
                $user = User::create(['name' => $name, 'country_id' => $country->id, 'phone' => $phone, 'password' => $password]);
                // The organizer knows this number: no SMS verification needed to score.
                $user->forceFill(['must_change_password' => true, 'phone_verified_at' => now()])->save();
            }

            return $competition->judges()->create(['user_id' => $user->id, 'status' => JudgeStatus::Accepted]);
        });

        $this->sms->send($phone, $password
            ? "Battle Game : vous êtes juré de « {$competition->name} ». Connectez-vous à l'application avec votre numéro et le mot de passe provisoire {$password} (à changer à la première connexion)."
            : "Battle Game : vous êtes juré de « {$competition->name} ». Retrouvez la compétition dans l'espace juré de l'application.");

        return [$judge, $password];
    }
}
