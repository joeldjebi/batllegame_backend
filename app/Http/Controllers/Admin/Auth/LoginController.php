<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Requests\Auth\EmailLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Platform super-admin login (email + password), on its own URL and guard. Only platform admins
 * can sign in here; failures never reveal whether the account exists.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(EmailLoginRequest $request): RedirectResponse
    {
        $credentials = $request->credentials();
        $throttleKey = 'admin-login:'.$request->ip().'|'.$credentials['email'];

        if (RateLimiter::tooManyAttempts($throttleKey, config('admin.login_attempts_per_minute'))) {
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($throttleKey).' secondes.',
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password) || ! $user->isPlatformAdmin()) {
            RateLimiter::hit($throttleKey, 60);
            Log::warning('Failed platform admin login', ['email' => $credentials['email'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $request->session()->put(AdminIdleTimeout::SESSION_KEY, now()->timestamp);

        Log::info('Platform admin logged in', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
