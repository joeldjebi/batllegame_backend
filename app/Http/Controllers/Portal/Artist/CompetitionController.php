<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\ArtistJourney;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Ma compétition »: the journey of the signed-in artist in one competition.
 */
class CompetitionController extends Controller
{
    public function show(Request $request, Competition $competition, ArtistJourney $journey): View
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)->with(['preselectionEntry', 'payments', 'user'])->firstOr(fn () => abort(404));

        return view('portal.artist.competition', [
            'competition' => $competition->load(['organizer', 'preselection']),
            'participant' => $participant,
            ...$journey->for($participant),
        ]);
    }
}
