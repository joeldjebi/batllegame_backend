<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web areas: an account created with a temporary password is sent to the
 * given password page until it changes it. Usage: fresh.password:password.edit
 */
class RequireFreshPassword
{
    public function handle(Request $request, Closure $next, string $passwordRoute): Response
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route($passwordRoute);
        }

        return $next($request);
    }
}
