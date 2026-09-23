<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * An artist registering to a competition (mobile API and web artist area).
 * Authorization is the ParticipantPolicy's job.
 */
class RegistrationService
{
    public function register(User $artist, Competition $competition, string $stageName): Participant
    {
        $participant = new Participant([
            'stage_name' => $stageName,
            // Paid competitions: the artist only counts once the fee is paid.
            'status' => $competition->requiresPayment()
                ? ParticipantStatus::PaymentPending
                : $competition->participantStatusAfterRegistration(),
        ]);
        $participant->user()->associate($artist);

        try {
            // Savepoint: a unique violation (concurrent double registration) must not abort an outer transaction.
            DB::transaction(fn () => $competition->participants()->save($participant));
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Vous êtes déjà inscrit à cette compétition.');
        }

        return $participant;
    }
}
