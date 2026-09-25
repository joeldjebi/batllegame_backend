<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OrganizerSignupRequest;
use App\Models\Country;
use App\Services\OrganizerSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * « Créer mon espace organisateur »: for a visitor (account + organizer) or a
 * signed-in back-office user (one more organizer). Pending until verified.
 */
class OrganizerSignupController extends Controller
{
    public function create(): View
    {
        abort_unless(config('organizers.self_signup'), 404);

        return view('auth.organizer-signup', [
            'countries' => Country::query()->active()->get(),
            'user' => Auth::guard('web')->user(),
        ]);
    }

    public function store(OrganizerSignupRequest $request, OrganizerSignupService $signups): RedirectResponse
    {
        abort_unless(config('organizers.self_signup'), 404);

        if ($user = $request->user('web')) {
            $organizer = $signups->create($user, $request->validated());
        } else {
            [$organizer, $user] = $signups->register($request->validated(), Country::query()->findOrFail($request->integer('country_id')), $request->e164Phone(), $request->validated());

            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('organizers.show', $organizer)
            ->with('status', "Espace « {$organizer->name} » créé. Préparez vos compétitions : les inscriptions pourront ouvrir dès que Battle Game aura vérifié votre organisateur.");
    }
}
