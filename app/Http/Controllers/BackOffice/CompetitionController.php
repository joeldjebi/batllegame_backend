<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\CompetitionRequest;
use App\Models\Competition;
use App\Models\Organizer;
use App\Services\CompetitionDuplicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * $competition is resolved through $organizer->competitions() (scoped binding),
 * so a competition of another organizer is a 404 before any policy runs.
 */
class CompetitionController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array SORTS = [
        'recent' => 'Plus récentes',
        'name' => 'Nom (A → Z)',
        'registration' => 'Fin des inscriptions',
        'participants' => 'Participants',
    ];

    /**
     * The organizer's competitions: search, filters, sort, pagination.
     */
    public function index(Request $request, Organizer $organizer): View
    {
        $this->authorize('viewAny', [Competition::class, $organizer]);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CompetitionStatus::class)],
            'discipline' => ['nullable', Rule::enum(Discipline::class)],
            'mode' => ['nullable', Rule::enum(CompetitionMode::class)],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);
        $sort = $filters['sort'] ?? 'recent';

        $competitions = $organizer->competitions()
            ->withCount('participants')
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->whereLike('name', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['discipline'] ?? null, fn ($q, $discipline) => $q->where('discipline', $discipline))
            ->when($filters['mode'] ?? null, fn ($q, $mode) => $q->where('mode', $mode))
            ->when($sort === 'recent', fn ($q) => $q->latest()->orderByDesc('id'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($sort === 'registration', fn ($q) => $q->orderByRaw('registration_ends_at is null')->orderBy('registration_ends_at'))
            ->when($sort === 'participants', fn ($q) => $q->orderByDesc('participants_count')->orderByDesc('id'))
            ->paginate(12)
            ->withQueryString();

        return view('competitions.index', [
            'organizer' => $organizer,
            'competitions' => $competitions,
            'counts' => $organizer->competitions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => $filters,
            'sorts' => self::SORTS,
            'sort' => $sort,
        ]);
    }

    public function duplicate(Request $request, Organizer $organizer, Competition $competition, CompetitionDuplicator $duplicator): RedirectResponse
    {
        $this->authorize('view', $competition);
        $this->authorize('create', [Competition::class, $organizer]);

        $copy = $duplicator->duplicate($competition, $request->user());

        return redirect()->route('organizers.competitions.show', [$organizer, $copy])
            ->with('status', 'Compétition dupliquée en brouillon : ajustez les dates et publiez-la.');
    }

    public function store(CompetitionRequest $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('create', [Competition::class, $organizer]);

        $competition = new Competition($request->competitionData());
        $competition->slug = Competition::uniqueSlug($competition->name);
        $competition->status = CompetitionStatus::Draft;
        $competition->organizer()->associate($organizer);
        $competition->creator()->associate($request->user());
        $competition->save();

        return redirect()->route('organizers.competitions.show', [$organizer, $competition])
            ->with('status', 'Compétition créée en brouillon.');
    }

    public function show(Organizer $organizer, Competition $competition): View
    {
        $this->authorize('view', $competition);

        return view('competitions.show', [
            'organizer' => $organizer,
            'competition' => $competition->load([
                'phases.groups.standings.participant',
                'phases.matches' => fn ($q) => $q->orderBy('group_id')->orderBy('bracket')->orderBy('round')->orderBy('bracket_position'),
                'phases.matches.slots.participant',
                'phases.matches.group',
                'phases.stages.performances.participant',
                'participants.user',
                'participants.payments' => fn ($q) => $q->latest(),
                'preselection.entries.participant',
                'judges.user',
                'criteria',
            ]),
        ]);
    }

    public function update(CompetitionRequest $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('update', $competition);

        $competition->update($request->competitionData());

        return back()->with('status', 'Compétition mise à jour.');
    }

    public function updateStatus(Request $request, Organizer $organizer, Competition $competition): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(CompetitionStatus::class)]]);
        $status = CompetitionStatus::from($validated['status']);

        $this->authorize('changeStatus', [$competition, $status]);

        // Artists and fans must know what the competition is about and what can be won.
        if ($status === CompetitionStatus::Registration && (blank($competition->description) || $competition->prizeList() === [])) {
            throw CompetitionFlowException::presentationMissing();
        }

        $competition->update(['status' => $status]);

        return back()->with('status', "Statut : {$status->label()}.");
    }

    public function destroy(Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('delete', $competition);

        $competition->delete();

        return redirect()->route('organizers.competitions.index', $organizer)->with('status', "« {$competition->name} » supprimée.");
    }
}
