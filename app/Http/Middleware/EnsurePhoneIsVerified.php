<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only users with a verified phone number may go through (required to vote).
 */
class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedPhone()) {
            abort(403, 'Vous devez vérifier votre numéro de téléphone.');
        }

        return $next($request);
    }
}
