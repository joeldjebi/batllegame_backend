<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Simulated registration fee payment (no provider yet).
 */
class PaymentController extends Controller
{
    public function store(Request $request, Competition $competition, PaymentService $payments): JsonResponse
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)->firstOr(fn () => abort(404));

        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'simulate_failure' => ['sometimes', 'boolean'],
        ]);

        $payment = $payments->simulate($participant, PaymentMethod::from($validated['method']), ! $request->boolean('simulate_failure'));

        return response()->json([
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at,
                'participant_status' => $participant->fresh()->status,
            ],
        ], $payment->paid_at ? 201 : 402);
    }
}
