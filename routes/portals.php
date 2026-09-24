<?php

use App\Http\Controllers\Portal\Artist\CompetitionController as ArtistCompetitionController;
use App\Http\Controllers\Portal\Artist\DashboardController as ArtistDashboardController;
use App\Http\Controllers\Portal\Artist\ParticipationController as ArtistParticipationController;
use App\Http\Controllers\Portal\Artist\PaymentController as ArtistPaymentController;
use App\Http\Controllers\Portal\Artist\PreselectionController as ArtistPreselectionController;
use App\Http\Controllers\Portal\Artist\ProfileController as ArtistProfileController;
use App\Http\Controllers\Portal\Fan\CompetitionController as FanCompetitionController;
use App\Http\Controllers\Portal\Fan\PreselectionController as FanPreselectionController;
use App\Http\Controllers\Portal\Fan\VerificationController;
use App\Http\Controllers\Portal\Fan\VoteController;
use App\Http\Controllers\Portal\Jury\CompetitionController as JuryCompetitionController;
use App\Http\Controllers\Portal\Jury\PasswordController;
use App\Http\Controllers\Portal\Jury\PreselectionController as JuryPreselectionController;
use App\Http\Controllers\Portal\PortalAuthController;
use Illuminate\Support\Facades\Route;

/*
| Web portals for phone-based accounts, each with its own login URL, until
| the mobile app ships: /jury (judges), /artiste (participants), /vote (public).
| Nested resources use scopeBindings(): a {match} or {stage} is always looked
| up through its competition.
*/

$authRoutes = function (bool $withRegistration): void {
    Route::middleware('guest:'.($withRegistration ? 'member' : 'jury'))->group(function () use ($withRegistration) {
        Route::get('login', [PortalAuthController::class, 'create'])->name('login');
        Route::post('login', [PortalAuthController::class, 'store'])->middleware('throttle:6,1');

        if ($withRegistration) {
            Route::get('inscription', [PortalAuthController::class, 'registerForm'])->name('register');
            Route::post('inscription', [PortalAuthController::class, 'register'])->middleware('throttle:6,1');
        }
    });
};

// Judges.
Route::prefix('jury')->name('jury.')->group(function () use ($authRoutes) {
    $authRoutes(false);

    Route::middleware(['auth:jury', 'deny.admins'])->group(function () {
        Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
        Route::get('mot-de-passe', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('mot-de-passe', [PasswordController::class, 'update'])->name('password.update');

        Route::middleware('jury.access')->scopeBindings()->group(function () {
            Route::get('/', [JuryCompetitionController::class, 'index'])->name('dashboard');
            Route::get('competitions/{competition:slug}', [JuryCompetitionController::class, 'show'])->name('competitions.show');
            Route::get('competitions/{competition:slug}/matches/{match}', [JuryCompetitionController::class, 'match'])->name('competitions.matches.show');
            Route::post('competitions/{competition:slug}/matches/{match}/scores', [JuryCompetitionController::class, 'score'])->name('competitions.matches.scores.store');
            Route::get('competitions/{competition:slug}/preselection', [JuryPreselectionController::class, 'index'])->name('competitions.preselection');
            Route::get('competitions/{competition:slug}/preselection/{entry}', [JuryPreselectionController::class, 'show'])->name('competitions.preselection.entries.show');
            Route::post('competitions/{competition:slug}/preselection/{entry}/scores', [JuryPreselectionController::class, 'score'])->name('competitions.preselection.scores.store');
        });
    });
});

// Artists (participants).
Route::prefix('artiste')->name('artist.')->group(function () use ($authRoutes) {
    $authRoutes(true);

    Route::middleware(['auth:member', 'deny.admins'])->scopeBindings()->group(function () {
        Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
        Route::get('/', ArtistDashboardController::class)->name('dashboard');
        Route::get('profil', [ArtistProfileController::class, 'edit'])->name('profile.edit');
        Route::get('competitions/{competition:slug}', [ArtistCompetitionController::class, 'show'])->name('competitions.show');
        Route::put('profil', [ArtistProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
        Route::post('competitions/{competition:slug}/inscription', [ArtistParticipationController::class, 'register'])->name('competitions.register');
        Route::post('competitions/{competition:slug}/stages/{stage}/soumission', [ArtistParticipationController::class, 'submit'])
            ->middleware('throttle:10,1')
            ->name('competitions.stages.submit');
        Route::get('competitions/{competition:slug}/paiement', [ArtistPaymentController::class, 'show'])->name('competitions.payment');
        Route::post('competitions/{competition:slug}/paiement', [ArtistPaymentController::class, 'store'])->middleware('throttle:10,1');
        Route::post('competitions/{competition:slug}/preselection', [ArtistPreselectionController::class, 'submit'])
            ->middleware('throttle:10,1')
            ->name('competitions.preselection.submit');
    });
});

// Public: browsing is open to everyone, voting needs an account with a verified phone.
Route::prefix('vote')->name('fan.')->group(function () use ($authRoutes) {
    $authRoutes(true);

    Route::scopeBindings()->group(function () {
        Route::get('/', [FanCompetitionController::class, 'index'])->name('dashboard');
        Route::get('competitions/{competition:slug}', [FanCompetitionController::class, 'show'])->name('competitions.show');
        // Shareable page of one pre-selection entry (link previews, like button).
        Route::get('competitions/{competition:slug}/prestations/{entry}', [FanPreselectionController::class, 'show'])->name('competitions.preselection.entry');

        Route::middleware(['auth:member', 'deny.admins'])->group(function () {
            Route::post('logout', [PortalAuthController::class, 'destroy'])->name('logout');
            Route::get('verification', [VerificationController::class, 'show'])->name('verification.show');
            Route::post('verification/code', [VerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');
            Route::post('verification', [VerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
            Route::post('competitions/{competition:slug}/matches/{match}/votes', [VoteController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('competitions.matches.votes.store');
            Route::post('competitions/{competition:slug}/preselection/{entry}/like', [FanPreselectionController::class, 'like'])
                ->middleware('throttle:30,1')
                ->name('competitions.preselection.like');
            Route::delete('competitions/{competition:slug}/preselection/like', [FanPreselectionController::class, 'unlike'])->name('competitions.preselection.unlike');
        });
    });
});
