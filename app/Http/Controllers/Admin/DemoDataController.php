<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoDataPurger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Super-admin: remove the data created by the local seeders in one go.
 */
class DemoDataController extends Controller
{
    public function destroy(Request $request, DemoDataPurger $purger): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', 'in:VIDER'],
            'with_accounts' => ['boolean'],
        ], [
            'confirmation.in' => 'Saisissez VIDER pour confirmer.',
            'confirmation.required' => 'Saisissez VIDER pour confirmer.',
        ]);

        $result = $purger->purge($request->boolean('with_accounts'), $request->user('admin'));

        $message = "Données de test supprimées : {$result['competitions']} compétition(s), {$result['users']} compte(s)"
            .($result['organizers'] ? ", {$result['organizers']} organisateur(s)" : '')
            .($result['files'] ? ", {$result['files']} fichier(s)" : '').'.';

        return redirect()->route('admin.dashboard')->with('status', $message);
    }
}
