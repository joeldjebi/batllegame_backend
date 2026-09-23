<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\StoreOrganizerRequest;
use App\Models\Competition;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizerController extends Controller
{
    public function show(Organizer $organizer): View
    {
        $this->authorize('viewAny', [Competition::class, $organizer]);

        return view('organizers.show', [
            'organizer' => $organizer,
            'competitions' => $organizer->competitions()->withCount('participants')->latest()->get(),
            'members' => $organizer->members()->with('user')->get(),
        ]);
    }

    public function update(StoreOrganizerRequest $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('update', $organizer);

        $organizer->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $organizer->logo_path = $request->file('logo')->store('organizers', 'public');
        }

        $organizer->save();

        return back()->with('status', 'Profil mis à jour.');
    }
}
