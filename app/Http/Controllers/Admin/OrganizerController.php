<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform admin: verification and suspension of organizers.
 */
class OrganizerController extends Controller
{
    public function index(Request $request): View
    {
        $organizers = Organizer::query()
            ->withCount('competitions')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(30);

        return view('admin.organizers.index', ['organizers' => $organizers]);
    }

    public function updateStatus(Request $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('moderate', $organizer);

        $validated = $request->validate(['status' => ['required', Rule::enum(OrganizerStatus::class)]]);
        $status = OrganizerStatus::from($validated['status']);

        $organizer->forceFill([
            'status' => $status,
            'verified_at' => $status === OrganizerStatus::Verified ? ($organizer->verified_at ?? now()) : $organizer->verified_at,
        ])->save();

        return back()->with('status', "{$organizer->name} : {$status->label()}.");
    }
}
