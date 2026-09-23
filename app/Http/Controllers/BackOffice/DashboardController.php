<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Organizers the current user belongs to.
     */
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'organizers' => $request->user()->organizers()->orderBy('name')->get(),
        ]);
    }
}
