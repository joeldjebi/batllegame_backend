<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\PaymentStatus;
use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\CompetitionRequest;
use App\Http\Requests\BackOffice\StoreCompetitionRequest;
use App\Models\Competition;
use App\Models\Organizer;
use App\Services\Competition\PhaseCreator;
use App\Services\CompetitionDuplicator;
use App\Services\CompetitionGuideDraft;
use App\Services\PreselectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->withCount(['participants', 'payments as paid_payments_count' => fn ($q) => $q->where('status', PaymentStatus::Paid)])
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

    /**
     * Full-page creation: the competition, its optional pre-selection, then its format.
     */
    public function create(Organizer $organizer): View
    {
        $this->authorize('create', [Competition::class, $organizer]);

        return view('competitions.create', ['organizer' => $organizer]);
    }

    /**
     * Three steps in one form: the competition, its optional pre-selection, then the first phase
     * (groups bring the final bracket along, so the calendar is known up to the final).
     */
    public function store(StoreCompetitionRequest $request, Organizer $organizer, PreselectionService $preselections, PhaseCreator $phases): RedirectResponse
    {
        $this->authorize('create', [Competition::class, $organizer]);

        [$competition, $created] = DB::transaction(function () use ($request, $organizer, $preselections, $phases): array {
            $competition = new Competition($request->competitionData());
            $competition->slug = Competition::uniqueSlug($competition->name);
            $competition->status = CompetitionStatus::Draft;
            $competition->organizer()->associate($organizer);
            $competition->creator()->associate($request->user());
            $competition->save();

            if ($preselection = $request->preselectionData()) {
                $preselections->configure($competition, $preselection);
            }

            $created = ($phase = $request->phaseData()) ? $phases->create($competition, $phase) : null;

            return [$competition, $created];
        });

        $steps = array_filter([
            $competition->preselection ? 'la présélection' : null,
            $created ? ($created['final'] ? 'les poules et la phase finale' : 'la première phase') : null,
        ]);

        return redirect()->route('organizers.competitions.show', [$organizer, $competition])
            ->with('status', 'Compétition créée en brouillon'.($steps ? ', avec '.implode(' et ', $steps) : '').'. Complétez la présentation, les récompenses et le calendrier.');
    }

    public function show(Organizer $organizer, Competition $competition): View
    {
        $this->authorize('view', $competition);

        return view('competitions.show', [
            'organizer' => $organizer,
            'competition' => $competition->load([
                'phases.groups.standings.participant',
                'phases.matches' => fn ($q) => $q->orderBy('group_id')->orderBy('bracket')->orderBy('round')->orderBy('bracket_position'),
                'phases.matches.slots.participant.user',
                'phases.matches.group',
                'phases.stages.performances.participant.user',
                'phases.stages.matches.group',
                'phases.stages.matches.slots.participant.user',
                'participants.user.country',
                'participants.user.city',
                'participants.user.commune',
                'participants.preselectionEntry',
                'participants.payments' => fn ($q) => $q->latest(),
                'preselection.entries.participant.user',
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

    /**
     * Pre-fill the schedule and the regulations from the configuration, in the settings form only:
     * nothing is saved (nor public) until the organizer reviews it and saves.
     */
    public function draftGuide(Organizer $organizer, Competition $competition, CompetitionGuideDraft $draft): RedirectResponse
    {
        $this->authorize('update', $competition);

        return redirect()->to(route('organizers.competitions.show', [$organizer, $competition]).'#settings')
            ->withInput($draft->make($competition))
            ->with('status', 'Brouillon du règlement prêt dans les paramètres : relisez-le, complétez-le puis enregistrez.');
    }

    public function destroy(Organizer $organizer, Competition $competition): RedirectResponse
    {
        $this->authorize('delete', $competition);

        $competition->delete();

        return redirect()->route('organizers.competitions.index', $organizer)->with('status', "« {$competition->name} » supprimée.");
    }
}
