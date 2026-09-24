<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ProfileRequest;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mon profil » of the member portals: profile photo, name, email and city.
 * The phone number (login) is not editable here.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.artist.profile', ['user' => $request->user()->load(['city', 'commune', 'country'])]);
    }

    public function update(ProfileRequest $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->updateProfile($request->user(), $request);

        return back()->with('status', 'Profil mis à jour.');
    }
}
