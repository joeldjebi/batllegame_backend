<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps platform admins out of every area but their console: they must go
 * through the admin login and its own guard.
 */
class DenyPlatformAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPlatformAdmin()) {
            // The guard authenticated by the route middleware is the default one here.
            Auth::guard()->logout();
            $request->session()->regenerateToken();

            return redirect()->guest(route(match (true) {
                $request->routeIs('jury.*') => 'jury.login',
                $request->routeIs('artist.*') => 'artist.login',
                $request->routeIs('fan.*') => 'fan.login',
                default => 'login',
            }));
        }

        return $next($request);
    }
}
