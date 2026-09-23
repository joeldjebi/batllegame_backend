<?php

namespace App\Http\Controllers\Portal;

use App\Enums\JudgeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Country;
use App\Models\User;
use App\Services\PhoneVerificationService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phone + password login (and sign-up) for the jury, artist and public portals.
 * Each portal has its own URL; the portal is derived from the route name.
 */
class PortalAuthController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.login', ['portal' => Portal::current(), 'countries' => Country::query()->active()->get()]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $portal = Portal::current();
        $credentials = $request->credentials();
        $user = User::query()->where('phone', $credentials['phone'])->first();

        // Same message for every refusal: never reveal which accounts exist.
        $valid = $user !== null
            && Hash::check($credentials['password'], $user->password)
            && ! $user->isPlatformAdmin()
            && ($portal->key !== 'jury' || $user->judgeAssignments()->where('status', JudgeStatus::Accepted)->exists());

        if (! $valid) {
            throw ValidationException::withMessages(['phone' => 'Numéro ou mot de passe incorrect.']);
        }

        Auth::guard($portal->guard)->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route($portal->home));
    }

    public function registerForm(): View
    {
        $portal = Portal::current();
        abort_unless($portal->canRegister, 404);

        return view('portal.auth.register', ['portal' => $portal, 'countries' => Country::query()->active()->get()]);
    }

    public function register(RegisterRequest $request, PhoneVerificationService $verification): RedirectResponse
    {
        $portal = Portal::current();
        abort_unless($portal->canRegister, 404);

        $user = User::create([
            'name' => $request->validated('name'),
            'country_id' => $request->integer('country_id'),
            'phone' => $request->e164Phone(),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        Auth::guard($portal->guard)->login($user);
        $request->session()->regenerate();
        $verification->sendCode($user);

        // After the SMS verification, artists land on their own space.
        $request->session()->put('url.intended', route($portal->home));

        return redirect()->route('fan.verification.show')->with('status', 'Compte créé ! Entrez le code reçu par SMS pour pouvoir voter.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $portal = Portal::current();

        // Only this portal's guard: other sessions of the browser stay open.
        Auth::guard($portal->guard)->logout();
        $request->session()->regenerateToken();

        return redirect()->route($portal->key.'.login');
    }
}
