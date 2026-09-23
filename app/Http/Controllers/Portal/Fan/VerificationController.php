<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phone verification by SMS code: required to vote.
 */
class VerificationController extends Controller
{
    public function __construct(private PhoneVerificationService $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return redirect()->route('fan.dashboard');
        }

        return view('portal.fan.verification');
    }

    public function send(Request $request): RedirectResponse
    {
        $this->verification->sendCode($request->user());

        return back()->with('status', 'Un nouveau code vous a été envoyé par SMS.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $this->verification->verify($request->user(), (string) $request->input('code'));

        return redirect()->intended(route('fan.dashboard'))->with('status', 'Numéro vérifié : vous pouvez voter !');
    }
}
