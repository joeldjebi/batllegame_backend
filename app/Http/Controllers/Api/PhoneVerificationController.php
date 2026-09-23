<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(private PhoneVerificationService $verification) {}

    public function send(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return response()->json(['message' => 'Numéro déjà vérifié.']);
        }

        $this->verification->sendCode($request->user());

        return response()->json(['message' => 'Code envoyé.'], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $this->verification->verify($request->user(), $request->string('code'));

        return response()->json(['message' => 'Numéro vérifié.']);
    }
}
