<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin guard only ever holds platform admins; anyone else is logged out.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->user()?->isPlatformAdmin()) {
            Auth::guard('admin')->logout();

            abort(403);
        }

        return $next($request);
    }
}
