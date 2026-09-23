<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\StoreOrganizerRequest;
use App\Models\Competition;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizerController extends Controller
{
    /**
     * Any user can create an organizer; it stays pending until a platform admin verifies it.
     */
    public function store(StoreOrganizerRequest $request): RedirectResponse
    {
        $organizer = DB::transaction(function () use ($request): Organizer {
            $organizer = new Organizer($request->safe()->except('logo'));
            $organizer->slug = Organizer::uniqueSlug($organizer->name);

            if ($request->hasFile('logo')) {
                $organizer->logo_path = $request->file('logo')->store('organizers', 'public');
            }

            $organizer->save();
            $organizer->users()->attach($request->user(), ['role' => OrganizerRole::Owner]);

            return $organizer;
        });

        return redirect()->route('organizers.show', $organizer)
            ->with('status', 'Organisateur créé. Il sera vérifié par la plateforme.');
    }

    public function show(Organizer $organizer): View
    {
        $this->authorize('viewAny', [Competition::class, $organizer]);

        return view('organizers.show', [
            'organizer' => $organizer,
            'competitions' => $organizer->competitions()->latest()->get(),
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
