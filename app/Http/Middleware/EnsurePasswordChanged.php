<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created with a temporary password (judges) must change it first.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Changez votre mot de passe provisoire pour continuer.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
