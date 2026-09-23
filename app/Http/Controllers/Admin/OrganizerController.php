<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PublicVote;
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
            ->withCount(['competitions', 'members'])
            ->with(['members' => fn ($q) => $q->where('role', 'owner')->with('user:id,name,email')])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $search) => $q->whereLike('name', "%{$search}%"))
            ->orderByRaw("case status when 'en_attente' then 0 when 'verifie' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizers.index', [
            'organizers' => $organizers,
            'counts' => Organizer::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'competitionsCount' => Competition::query()->count(),
        ]);
    }

    public function show(Organizer $organizer): View
    {
        $competitionIds = $organizer->competitions()->select('id');

        return view('admin.organizers.show', [
            'organizer' => $organizer,
            'members' => $organizer->members()->with('user.country')->orderByRaw("case role when 'owner' then 0 when 'admin' then 1 else 2 end")->get(),
            'competitions' => $organizer->competitions()->withTrashed()->withCount(['participants', 'phases', 'matches', 'publicVotes'])->latest()->get(),
            'stats' => [
                'participants' => Participant::query()->whereIn('competition_id', $competitionIds)->count(),
                'votes' => PublicVote::query()->whereIn('competition_id', $competitionIds)->count(),
                'matches' => BattleMatch::query()->whereIn('competition_id', $competitionIds)->count(),
            ],
        ]);
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
