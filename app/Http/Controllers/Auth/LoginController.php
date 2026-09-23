<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Organizer back-office session login (email + password).
 * Platform admins are refused here: they have their own login (routes/admin.php).
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(EmailLoginRequest $request): RedirectResponse
    {
        // Platform admins must use the admin login: same message, so the page never
        // reveals that an admin account exists behind this email.
        $credentials = $request->credentials();
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if ($user === null || ! Auth::getProvider()->validateCredentials($user, $credentials) || $user->isPlatformAdmin()) {
            throw ValidationException::withMessages(['email' => 'Email ou mot de passe incorrect.']);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
