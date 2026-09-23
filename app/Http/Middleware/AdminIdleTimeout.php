<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the admin session after config('admin.idle_timeout') minutes of inactivity.
 */
class AdminIdleTimeout
{
    public const string SESSION_KEY = 'admin_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = $request->session()->get(self::SESSION_KEY);
        $timeout = config('admin.idle_timeout') * 60;

        if ($lastActivity !== null && now()->timestamp - $lastActivity > $timeout) {
            Auth::guard('admin')->logout();
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Session expirée après inactivité. Reconnectez-vous.']);
        }

        $request->session()->put(self::SESSION_KEY, now()->timestamp);

        return $next($request);
    }
}
