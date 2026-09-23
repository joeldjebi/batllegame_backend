<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackOffice\CompetitionController;
use App\Http\Controllers\BackOffice\CriterionController;
use App\Http\Controllers\BackOffice\DashboardController;
use App\Http\Controllers\BackOffice\JudgeController;
use App\Http\Controllers\BackOffice\MatchController;
use App\Http\Controllers\BackOffice\OrganizerController;
use App\Http\Controllers\BackOffice\OrganizerMemberController;
use App\Http\Controllers\BackOffice\ParticipantController;
use App\Http\Controllers\BackOffice\PerformanceController;
use App\Http\Controllers\BackOffice\PhaseController;
use App\Http\Controllers\BackOffice\StageController;
use Illuminate\Support\Facades\Route;

/*
| Back-office (Blade) for organizers.
|
| Every child resource is nested under its organizer and competition with
| scopeBindings(): a {competition} is looked up through $organizer->competitions(),
| a {phase} through $competition->phases(), etc. A child is never loaded by id alone.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'organizer.area'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('organizers', [OrganizerController::class, 'store'])->name('organizers.store');

    Route::scopeBindings()
        ->prefix('organizers/{organizer}')
        ->name('organizers.')
        ->group(function () {
            Route::get('/', [OrganizerController::class, 'show'])->name('show');
            Route::put('/', [OrganizerController::class, 'update'])->name('update');

            Route::post('members', [OrganizerMemberController::class, 'store'])->name('members.store');
            Route::patch('members/{member}', [OrganizerMemberController::class, 'update'])->name('members.update');
            Route::delete('members/{member}', [OrganizerMemberController::class, 'destroy'])->name('members.destroy');

            Route::post('competitions', [CompetitionController::class, 'store'])->name('competitions.store');

            Route::prefix('competitions/{competition}')->name('competitions.')->group(function () {
                Route::get('/', [CompetitionController::class, 'show'])->name('show');
                Route::put('/', [CompetitionController::class, 'update'])->name('update');
                Route::patch('status', [CompetitionController::class, 'updateStatus'])->name('status');
                Route::delete('/', [CompetitionController::class, 'destroy'])->name('destroy');

                Route::post('phases', [PhaseController::class, 'store'])->name('phases.store');
                Route::put('phases/{phase}', [PhaseController::class, 'update'])->name('phases.update');
                Route::delete('phases/{phase}', [PhaseController::class, 'destroy'])->name('phases.destroy');
                Route::post('phases/{phase}/start', [PhaseController::class, 'start'])->name('phases.start');

                Route::put('stages/{stage}', [StageController::class, 'update'])->name('stages.update');
                Route::post('stages/{stage}/open-submissions', [StageController::class, 'openSubmissions'])->name('stages.open-submissions');
                Route::post('stages/{stage}/open-voting', [StageController::class, 'openVoting'])->name('stages.open-voting');

                Route::patch('performances/{performance}', [PerformanceController::class, 'review'])->name('performances.review');
                Route::post('matches/{match}/captations', [PerformanceController::class, 'captation'])->name('matches.captations.store');

                Route::put('matches/{match}', [MatchController::class, 'update'])->name('matches.update');
                Route::post('matches/{match}/open-voting', [MatchController::class, 'openVoting'])->name('matches.open-voting');
                Route::post('matches/{match}/close', [MatchController::class, 'close'])->name('matches.close');

                Route::post('criteria', [CriterionController::class, 'store'])->name('criteria.store');
                Route::put('criteria/{criterion}', [CriterionController::class, 'update'])->name('criteria.update');
                Route::delete('criteria/{criterion}', [CriterionController::class, 'destroy'])->name('criteria.destroy');

                Route::post('judges', [JudgeController::class, 'store'])->name('judges.store');
                Route::delete('judges/{judge}', [JudgeController::class, 'destroy'])->name('judges.destroy');

                Route::patch('participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
            });
        });

});
