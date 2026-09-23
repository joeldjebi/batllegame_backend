<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Back-office password change (mandatory after an account was created with a temporary password).
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $request->user()->forceFill(['password' => $validated['password'], 'must_change_password' => false])->save();

        return redirect()->route('dashboard')->with('status', 'Mot de passe mis à jour.');
    }
}
