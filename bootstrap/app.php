<?php

use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\DenyPlatformAdmins;
use App\Http\Middleware\EnsureJudgeAccess;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\RequireFreshPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix(config('admin.path'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')->group(base_path('routes/portals.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'phone.verified' => EnsurePhoneIsVerified::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'admin.idle' => AdminIdleTimeout::class,
            'organizer.area' => DenyPlatformAdmins::class,
            'password.changed' => EnsurePasswordChanged::class,
            'deny.admins' => DenyPlatformAdmins::class,
            'jury.access' => EnsureJudgeAccess::class,
            'fresh.password' => RequireFreshPassword::class,
        ]);

        // Each area sends guests to its own login page.
        $middleware->redirectGuestsTo(fn (Request $request) => route(match (true) {
            $request->routeIs('admin.*') => 'admin.login',
            $request->routeIs('jury.*') => 'jury.login',
            $request->routeIs('artist.*') => 'artist.login',
            $request->routeIs('fan.*') => 'fan.login',
            default => 'login',
        }));
        $middleware->redirectUsersTo(fn (Request $request) => route(match (true) {
            $request->routeIs('admin.*') => 'admin.dashboard',
            $request->routeIs('jury.*') => 'jury.dashboard',
            $request->routeIs('artist.*') => 'artist.dashboard',
            $request->routeIs('fan.*') => 'fan.dashboard',
            default => 'dashboard',
        }));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
