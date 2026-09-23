<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Enums\ParticipantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Participant;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Simulated checkout of the registration fee.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Competition $competition): View|RedirectResponse
    {
        $participant = $this->participantOf($request, $competition);

        if ($participant->status !== ParticipantStatus::PaymentPending) {
            return redirect()->route('artist.dashboard');
        }

        return view('portal.artist.payment', [
            'competition' => $competition->load('organizer'),
            'participant' => $participant,
            'lastFailure' => $participant->payments()->where('status', PaymentStatus::Failed)->latest()->first(),
        ]);
    }

    public function store(Request $request, Competition $competition, PaymentService $payments): RedirectResponse
    {
        $participant = $this->participantOf($request, $competition);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'outcome' => ['required', Rule::in(['succes', 'echec'])],
        ]);

        $payment = $payments->simulate($participant, PaymentMethod::from($validated['method']), $validated['outcome'] === 'succes');

        return $payment->status === PaymentStatus::Paid
            ? redirect()->route('artist.dashboard')->with('status', "Paiement confirmé ({$payment->reference}) : vous êtes inscrit à « {$competition->name} ».")
            : back()->withErrors(['payment' => 'Le paiement a échoué. Réessayez ou choisissez un autre moyen de paiement.']);
    }

    private function participantOf(Request $request, Competition $competition): Participant
    {
        return $competition->participants()->where('user_id', $request->user()->id)->firstOr(fn () => abort(404));
    }
}
