<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizerRole;
use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizerRequest;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PublicVote;
use App\Realtime\Channel;
use App\Realtime\Realtime;
use App\Services\BackOfficeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->with(['city', 'commune', 'members' => fn ($q) => $q->where('role', 'owner')->with('user:id,name,email')])
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

    /**
     * The super-admin creates an organizer with its owner account (organizers can also sign up themselves).
     */
    public function store(StoreOrganizerRequest $request, BackOfficeAccountService $accounts): RedirectResponse
    {
        $this->authorize('moderate', Organizer::class);

        [$organizer, $owner, $password] = DB::transaction(function () use ($request, $accounts): array {
            $organizer = new Organizer($request->safe()->only(['name', 'city_id', 'commune_id', 'description']));
            $organizer->slug = Organizer::uniqueSlug($organizer->name);
            $status = OrganizerStatus::from($request->validated('status'));
            $organizer->forceFill(['status' => $status, 'verified_at' => $status === OrganizerStatus::Verified ? now() : null])->save();

            [$owner, $password] = $accounts->findOrCreate(
                $request->validated('owner_email'),
                $request->validated('owner_name'),
                $request->country(),
                $request->validated('phone'),
                $organizer->name,
            );

            if ($owner->isPlatformAdmin()) {
                throw ValidationException::withMessages(['owner_email' => 'Le super-admin ne peut pas être propriétaire d\'un organisateur.']);
            }

            $organizer->users()->attach($owner, ['role' => OrganizerRole::Owner]);

            return [$organizer, $owner, $password];
        });

        return redirect()->route('admin.organizers.show', $organizer)->with('status', $password
            ? "Organisateur créé. Compte propriétaire créé pour {$owner->name} : mot de passe provisoire {$password} (envoyé par SMS)."
            : "Organisateur créé, {$owner->name} en est propriétaire.");
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

    public function updateStatus(Request $request, Organizer $organizer, Realtime $realtime): RedirectResponse
    {
        $this->authorize('moderate', $organizer);

        $validated = $request->validate(['status' => ['required', Rule::enum(OrganizerStatus::class)]]);
        $status = OrganizerStatus::from($validated['status']);

        $organizer->forceFill([
            'status' => $status,
            'verified_at' => $status === OrganizerStatus::Verified ? ($organizer->verified_at ?? now()) : $organizer->verified_at,
        ])->save();

        $realtime->push([Channel::organizer($organizer->id), Channel::ADMIN], 'organizer.status', ['organizer_id' => $organizer->id], match ($status) {
            OrganizerStatus::Verified => "« {$organizer->name} » est vérifié : vous pouvez ouvrir les inscriptions.",
            OrganizerStatus::Suspended => "« {$organizer->name} » a été suspendu par Battle Game.",
            default => null,
        });

        return back()->with('status', "{$organizer->name} : {$status->label()}.");
    }
}
