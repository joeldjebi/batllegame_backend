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

        // A city without communes posts no commune: clear the previous one.
        $organizer->fill([...$request->safe()->except('logo'), 'commune_id' => $request->validated('commune_id')]);

        if ($request->hasFile('logo')) {
            $organizer->forceFill(['logo_path' => $request->file('logo')->store('organizers', config('media.disk')), 'logo_disk' => config('media.disk')]);
        }

        $organizer->save();

        return back()->with('status', 'Profil mis à jour.');
    }
}
