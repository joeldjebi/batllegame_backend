<?php

namespace App\Http\Controllers\Portal\Artist;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\PreselectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreselectionController extends Controller
{
    public function submit(Request $request, Competition $competition, PreselectionService $preselections): RedirectResponse
    {
        $participant = $competition->participants()->where('user_id', $request->user()->id)->firstOr(fn () => abort(404));
        $rules = ($competition->preselection ?? abort(404))->rules;

        $request->validate([
            'media' => ['required', 'file', 'mimetypes:'.implode(',', $rules->acceptedMimeTypes()), 'max:'.($rules->mediaMaxSizeMb * 1024)],
        ], [
            'media.mimetypes' => 'Format non accepté pour la présélection.',
            'media.max' => "Le fichier dépasse la taille maximale de {$rules->mediaMaxSizeMb} Mo.",
        ]);

        $preselections->submit($participant, $request->file('media'));

        return back()->with('status', 'Votre prestation de présélection a bien été envoyée.');
    }
}
