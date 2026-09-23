<?php

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\Participant;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registration fees. No payment provider is plugged in yet: payments are
 * simulated (the artist picks a method and the outcome), but every attempt is
 * recorded like a real transaction so a provider can replace simulate() later.
 */
class PaymentService
{
    public function simulate(Participant $participant, PaymentMethod $method, bool $succeeds = true): Payment
    {
        return DB::transaction(function () use ($participant, $method, $succeeds): Payment {
            $participant = Participant::query()->lockForUpdate()->findOrFail($participant->id);
            $competition = $participant->competition;

            // A registration made before the fee was set (still « inscrit ») must pay too.
            $due = $participant->status === ParticipantStatus::PaymentPending
                || ($participant->status === ParticipantStatus::Registered && ! $participant->hasPaid());

            if (! $due || ! $competition->requiresPayment()) {
                throw CompetitionFlowException::nothingToPay();
            }

            $payment = new Payment;
            $payment->forceFill([
                'competition_id' => $competition->id,
                'participant_id' => $participant->id,
                'user_id' => $participant->user_id,
                'amount' => $competition->entry_fee,
                'currency' => $competition->currency,
                'method' => $method,
                'provider' => 'simulation',
                'reference' => 'BG-'.Str::upper(Str::random(10)),
                'status' => $succeeds ? PaymentStatus::Paid : PaymentStatus::Failed,
                'paid_at' => $succeeds ? now() : null,
                'failure_reason' => $succeeds ? null : 'Paiement refusé (simulation).',
            ])->save();

            if ($succeeds && $participant->status === ParticipantStatus::PaymentPending) {
                $participant->update(['status' => $competition->participantStatusAfterRegistration()]);
            }

            return $payment;
        });
    }
}
