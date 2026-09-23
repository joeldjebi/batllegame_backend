<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CompetitionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrganizerController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Platform administration (super-admin only).
|
| Mounted under config('admin.path') with the "admin." name prefix, on its own
| "admin" guard: an organizer session never grants access here, and the
| organizer login refuses platform admin accounts.
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth:admin', 'platform.admin', 'admin.idle'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('organizers', [OrganizerController::class, 'index'])->name('organizers.index');
    Route::get('organizers/{organizer}', [OrganizerController::class, 'show'])->name('organizers.show');
    Route::patch('organizers/{organizer}/status', [OrganizerController::class, 'updateStatus'])->name('organizers.status');

    Route::get('competitions', [CompetitionController::class, 'index'])->name('competitions.index');
    Route::get('organizers/{organizer}/competitions/{competition}', [CompetitionController::class, 'show'])
        ->scopeBindings()
        ->name('organizers.competitions.show');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});
