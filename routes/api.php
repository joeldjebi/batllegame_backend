<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\Judge\CompetitionController as JudgeCompetitionController;
use App\Http\Controllers\Api\JuryScoreController;
use App\Http\Controllers\Api\ParticipationController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Http\Controllers\Api\PublicVoteController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
| REST API for the Flutter app (artists, public, jury). Sanctum tokens.
|
| Competitions are addressed by slug; nested resources use scopeBindings()
| so a {match} is always looked up through $competition->matches().
*/

Route::get('countries', [CountryController::class, 'index'])->name('api.countries.index');

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('phone/send-code', [PhoneVerificationController::class, 'send'])->middleware('throttle:3,1')->name('phone.send');
        Route::post('phone/verify', [PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('phone.verify');
        Route::post('password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1')->name('password');
    });
});

Route::scopeBindings()
    ->prefix('competitions')
    ->name('api.competitions.')
    ->group(function () {
        Route::get('/', [CompetitionController::class, 'index'])->name('index');
        Route::get('{competition:slug}', [CompetitionController::class, 'show'])->name('show');
        Route::get('{competition:slug}/matches/{match}', [CompetitionController::class, 'showMatch'])->name('matches.show');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('{competition:slug}/registrations', [RegistrationController::class, 'store'])->name('registrations.store');

            Route::post('{competition:slug}/matches/{match}/votes', [PublicVoteController::class, 'store'])
                ->middleware(['phone.verified', 'throttle:30,1'])
                ->name('matches.votes.store');

            Route::post('{competition:slug}/matches/{match}/jury-scores', [JuryScoreController::class, 'store'])
                ->middleware('password.changed')
                ->name('matches.jury-scores.store');

            Route::get('{competition:slug}/stages/{stage}/submission', [SubmissionController::class, 'show'])->name('stages.submission.show');
            Route::post('{competition:slug}/stages/{stage}/submission', [SubmissionController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('stages.submission.store');
        });
    });

// Participant area.
Route::middleware('auth:sanctum')->get('me/participations', [ParticipationController::class, 'index'])->name('api.me.participations');

// Judge area: only the competitions the judge is assigned to.
Route::middleware(['auth:sanctum', 'password.changed'])
    ->prefix('judge')
    ->name('api.judge.')
    ->scopeBindings()
    ->group(function () {
        Route::get('competitions', [JudgeCompetitionController::class, 'index'])->name('competitions.index');
        Route::get('competitions/{competition:slug}', [JudgeCompetitionController::class, 'show'])->name('competitions.show');
        Route::get('competitions/{competition:slug}/matches/{match}', [JudgeCompetitionController::class, 'match'])->name('competitions.matches.show');
    });
