<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, PhoneVerificationService $verification): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'country_id' => $request->integer('country_id'),
            'phone' => $request->e164Phone(),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $verification->sendCode($user);

        return $this->tokenResponse($user, $request->validated('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();
        $user = User::query()->where('phone', $credentials['phone'])->first();

        // Platform admins never get mobile API tokens.
        if ($user === null || ! Hash::check($credentials['password'], $user->password) || $user->isPlatformAdmin()) {
            throw ValidationException::withMessages(['phone' => 'Numéro ou mot de passe incorrect.']);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Change the password (mandatory for accounts created with a temporary one).
     */
    public function changePassword(Request $request): UserResource
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $request->user()->forceFill(['password' => $validated['password'], 'must_change_password' => false])->save();

        return new UserResource($request->user()->load('country'));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('country'));
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('country')),
        ], $status);
    }
}
