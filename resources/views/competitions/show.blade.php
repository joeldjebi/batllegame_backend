@php
    use App\Enums\CompetitionMode;
    use App\Enums\CompetitionStatus;
    use App\Enums\Discipline;
    use App\Enums\MatchStatus;
    use App\Enums\ParticipantStatus;
    use App\Enums\PhaseType;
    use App\Enums\VoteMode;

    $user = auth()->user();
    $canUpdate = $user->can('update', $competition);
    $canRun = $user->can('runMatches', $competition);
    $canRegistrations = $user->can('manageRegistrations', $competition);
    $canDelete = $user->can('delete', $competition);

    // Seeded first (1, 2, 3…), then unseeded alphabetically.
    $participants = $competition->participants->sortBy(fn ($p) => [$p->seed ?? PHP_INT_MAX, Str::lower($p->stage_name)]);
    $editableStatuses = [ParticipantStatus::Validated, ParticipantStatus::Withdrawn, ParticipantStatus::Disqualified];
    $validated = $participants->where('status', ParticipantStatus::Validated)->count();
    $matches = $competition->phases->flatMap->matches;
    $played = $matches->whereIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count();
    $voting = $matches->where('status', MatchStatus::Voting)->count();
    $settings = $competition->settings;

    $nextStatuses = collect($competition->status->nextStatuses())->filter(fn ($s) => $user->can('changeStatus', [$competition, $s]));

    $usesJury = $competition->phases->contains(fn ($p) => $p->rules->usesJury());
    // Only groups produce qualifiers: nothing can follow an elimination phase.
    $lastPhase = $competition->phases->sortBy('position')->last();
    $canAddPhase = $lastPhase === null || $lastPhase->type === PhaseType::Groups;
    $checklist = [
        ['Description et récompenses', filled($competition->description) && $competition->prizeList() !== []],
        ['Au moins une phase', $competition->phases->isNotEmpty()],
        ['Critères de notation', ! $usesJury || $competition->criteria->isNotEmpty()],
        ['Au moins un juré', ! $usesJury || $competition->judges->isNotEmpty()],
        ...($competition->preselection ? [['Sélection de la présélection publiée', $competition->preselection->published_at !== null]] : []),
        ['2 participants validés minimum', $validated >= 2],
        ['Organisateur vérifié', $organizer->isVerified()],
    ];
    $ready = collect($checklist)->every(fn ($item) => $item[1]);
@endphp

<x-layouts.app :title="$competition->name">
    <div data-live="summary">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Tableau de bord' => route('dashboard'), $organizer->name => route('organizers.show', $organizer), $competition->name => null]">
        <x-slot:leading>
            <span class="hidden size-14 shrink-0 place-items-center rounded-2xl bg-brand-600 text-white shadow-lift sm:grid">
                <x-ui.icon :name="$competition->discipline->icon()" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:description>
            <x-ui.badge :value="$competition->status" />
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->discipline->icon()" variant="m" class="size-4" />{{ $competition->discipline->label() }}</span>
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-4" />{{ $competition->mode->label() }}</span>
            @if ($competition->locationLabel())<span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" variant="m" class="size-4" />{{ $competition->locationLabel() }}</span>@endif
            <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                <x-ui.icon name="link" variant="m" class="size-4" />
                <button type="button" class="font-mono text-xs hover:text-brand-600" x-on:click="navigator.clipboard.writeText(@js($competition->slug)); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-show="! copied">{{ $competition->slug }}</span><span x-show="copied" x-cloak class="text-emerald-600">copié !</span>
                </button>
            </span>
        </x-slot:description>
        <x-slot:actions>
            @foreach ($nextStatuses->reject(fn ($s) => $s === CompetitionStatus::Cancelled) as $next)
                <x-ui.confirm :action="route('organizers.competitions.status', [$organizer, $competition])" method="PATCH" :danger="false" icon="arrow-right-circle"
                    :title="'Passer en « '.$next->label().' » ?'"
                    :message="match ($next) {
                        CompetitionStatus::Registration => 'La compétition devient visible dans l\'application et les artistes peuvent s\'inscrire.',
                        CompetitionStatus::InProgress => 'Les inscriptions seront fermées.',
                        CompetitionStatus::Finished => 'La compétition sera clôturée définitivement.',
                        default => null,
                    }" confirm="Confirmer">
                    <x-slot:fields><input type="hidden" name="status" value="{{ $next->value }}"></x-slot:fields>
                    <x-ui.button variant="primary" icon="arrow-right-circle">
                        {{ match ($next) { CompetitionStatus::Registration => 'Ouvrir les inscriptions', CompetitionStatus::InProgress => 'Lancer la compétition', CompetitionStatus::Finished => 'Terminer', default => $next->label() } }}
                    </x-ui.button>
                </x-ui.confirm>
            @endforeach

            @if ($nextStatuses->contains(CompetitionStatus::Cancelled) || $canDelete)
                <x-ui.dropdown>
                    <x-slot:trigger><x-ui.button variant="secondary" icon="ellipsis-horizontal"><span class="sr-only">Plus d'actions</span></x-ui.button></x-slot:trigger>
                    @if ($nextStatuses->contains(CompetitionStatus::Cancelled))
                        <x-ui.confirm :action="route('organizers.competitions.status', [$organizer, $competition])" method="PATCH" title="Annuler la compétition ?" message="Les inscriptions, matchs et votes seront figés. Cette action est irréversible." confirm="Annuler la compétition">
                            <x-slot:fields><input type="hidden" name="status" value="annulee"></x-slot:fields>
                            <x-ui.dropdown-item icon="x-circle" danger>Annuler la compétition</x-ui.dropdown-item>
                        </x-ui.confirm>
                    @endif
                    @if ($canDelete)
                        <x-ui.confirm :action="route('organizers.competitions.destroy', [$organizer, $competition])" method="DELETE" title="Supprimer la compétition ?" :message="($competition->participants->count() ? $competition->participants->count().' inscrit(s) seront retirés, aucun n\'a payé. ' : '').'Elle disparaîtra du back-office et de l\'application.'" confirm="Supprimer">
                            <x-ui.dropdown-item icon="trash" danger>Supprimer</x-ui.dropdown-item>
                        </x-ui.confirm>
                    @endif
                </x-ui.dropdown>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-bo.lifecycle :competition="$competition" class="mb-8" />

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Participants" :value="$participants->count().($competition->max_participants ? ' / '.$competition->max_participants : '')" icon="users"
            :progress="$competition->max_participants ? $participants->count() / $competition->max_participants * 100 : null" :hint="$validated.' validé(s)'" />
        <x-ui.stat label="Phases" :value="$competition->phases->count()" icon="rectangle-stack" tone="blue" :hint="$competition->phases->where('status', \App\Enums\PhaseStatus::Finished)->count().' terminée(s)'" />
        <x-ui.stat label="Matchs joués" :value="$played.' / '.$matches->count()" icon="bolt" tone="red" :progress="$matches->count() ? $played / $matches->count() * 100 : null" :hint="$voting ? $voting.' vote(s) en cours' : 'Aucun vote en cours'" />
        <x-ui.stat label="Jury" :value="$competition->judges->count()" icon="scale" tone="green" :hint="$competition->criteria->count().' critère(s) de notation'" />
    </div>
    </div>

    <x-ui.tabs key="competition" :tabs="[
        'overview' => ['label' => 'Aperçu', 'icon' => 'home'],
        'phases' => ['label' => 'Phases & matchs', 'icon' => 'trophy', 'count' => $competition->phases->count()],
        'preselection' => ['label' => 'Présélection', 'icon' => 'funnel', 'count' => $competition->preselection?->entries->count()],
        'participants' => ['label' => 'Participants', 'icon' => 'users', 'count' => $participants->count()],
        'jury' => ['label' => 'Jury & critères', 'icon' => 'scale'],
        'settings' => ['label' => 'Paramètres', 'icon' => 'cog-6-tooth'],
    ]">
        {{-- Overview --}}
        <x-ui.tab-panel name="overview">
            <div data-live="tab-overview">
            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card title="Déroulé" description="Les phases s'enchaînent dans cet ordre" icon="queue-list" class="xl:col-span-2">
                    @forelse ($competition->phases as $phase)
                        <div class="relative flex gap-4 pb-8 last:pb-0">
                            @unless ($loop->last)<span class="absolute top-10 left-5 -ml-px h-[calc(100%-2.5rem)] w-0.5 bg-slate-200 dark:bg-white/10"></span>@endunless
                            <span @class([
                                'relative grid size-10 shrink-0 place-items-center rounded-xl ring-4 ring-white dark:ring-slate-900',
                                'bg-emerald-500 text-white' => $phase->status === \App\Enums\PhaseStatus::Finished,
                                'bg-brand-600 text-white shadow-lift' => $phase->status === \App\Enums\PhaseStatus::InProgress,
                                'bg-slate-100 text-slate-500 dark:bg-white/10' => $phase->status === \App\Enums\PhaseStatus::Pending,
                            ])><x-ui.icon :name="$phase->type->icon()" class="size-5" /></span>
                            <div class="min-w-0 flex-1 pt-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-white">Phase {{ $phase->position }} · {{ $phase->type->label() }}</p>
                                    <x-ui.badge :value="$phase->status" />
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $phase->effectiveMode()->label() }} ·
                                    {{ $phase->rules->voteMode->label() }}@if ($phase->rules->voteMode === VoteMode::Mixed) ({{ $phase->rules->juryWeight }} % jury / {{ $phase->rules->publicWeight }} % public)@endif ·
                                    {{ $phase->rules->rounds }} passage(s) de {{ $phase->rules->turnDuration }} s
                                    @if ($phase->type === PhaseType::Groups) · {{ $phase->rules->groupCount }} poule(s) de classement, {{ $phase->qualifiers_per_group }} qualifié(s) par poule @endif
                                </p>
                                @if ($phase->matches->isNotEmpty())
                                    @php $done = $phase->matches->whereIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count(); @endphp
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-brand-600" style="width: {{ round($done / $phase->matches->count() * 100) }}%"></div></div>
                                        <span class="text-xs text-slate-500 tabular-nums">{{ $done }}/{{ $phase->matches->count() }} {{ $phase->type === PhaseType::Groups ? 'poule(s) close(s)' : 'matchs' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-ui.empty icon="queue-list" title="Aucune phase" description="Ajoutez des poules, une élimination simple ou double pour structurer la compétition.">
                            @if ($canUpdate && $canAddPhase)<x-ui.button size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-phase')">Ajouter une phase</x-ui.button>@endif
                        </x-ui.empty>
                    @endforelse
                </x-ui.card>

                <div class="space-y-6">
                    <x-ui.card title="Préparation" icon="clipboard-document-check">
                        <x-slot:actions>
                            <x-ui.badge :tone="$ready ? 'green' : 'amber'">{{ collect($checklist)->where(1, true)->count() }}/{{ count($checklist) }}</x-ui.badge>
                        </x-slot:actions>
                        <ul class="space-y-3">
                            @foreach ($checklist as [$label, $ok])
                                <li class="flex items-center gap-3 text-sm">
                                    <span @class(['grid size-6 place-items-center rounded-full', 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' => $ok, 'bg-slate-100 text-slate-400 dark:bg-white/10' => ! $ok])>
                                        <x-ui.icon :name="$ok ? 'check' : 'minus'" variant="m" class="size-4" />
                                    </span>
                                    <span @class(['text-slate-700 dark:text-slate-200' => $ok, 'text-slate-400' => ! $ok])>{{ $label }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>

                    <x-ui.card title="Informations" icon="information-circle">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Fin des inscriptions</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $competition->registration_ends_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Frais</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $competition->entry_fee ? number_format($competition->entry_fee, 0, ',', ' ').' '.$competition->currency : 'Gratuit' }}</dd></div>
                            @if ($competition->requiresPayment())
                                @php $paidPayments = $competition->participants->flatMap->payments->where('status', \App\Enums\PaymentStatus::Paid); @endphp
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Frais encaissés (simulés)</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ number_format($paidPayments->sum('amount'), 0, ',', ' ') }} {{ $competition->currency }} · {{ $paidPayments->count() }} paiement(s)</dd></div>
                            @endif
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Vote du public</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $settings->publicVotingEnabled ? 'Activé' : 'Désactivé' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Résultats en direct</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $settings->showLiveResults ? 'Oui' : 'Après clôture' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Créée par</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $competition->creator?->name ?? '—' }}</dd></div>
                        </dl>
                    </x-ui.card>
                </div>
            </div>
            </div>
        </x-ui.tab-panel>

        {{-- Phases & matches --}}
        <x-ui.tab-panel name="phases" class="space-y-6">
            <div data-live="tab-phases">
            @if ($canUpdate)
                <div class="flex flex-wrap items-center justify-end gap-3">
                    @if ($lastPhase && $lastPhase->type === PhaseType::Groups)
                        <p class="mr-auto flex items-center gap-2 text-sm text-slate-500"><x-ui.icon name="information-circle" variant="m" class="size-4 shrink-0 text-brand-600" /> Terminez par une phase à élimination pour désigner le vainqueur.</p>
                    @elseif ($lastPhase)
                        <p class="mr-auto flex items-center gap-2 text-sm text-slate-500"><x-ui.icon name="flag" variant="m" class="size-4 shrink-0 text-slate-400" /> La phase {{ $lastPhase->position }} ({{ mb_strtolower($lastPhase->type->label()) }}) désigne le vainqueur : aucune phase ne peut la suivre.</p>
                    @endif
                    @if ($canAddPhase)
                        <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-phase')">Ajouter une phase</x-ui.button>
                    @endif
                </div>
            @endif

            @forelse ($competition->phases as $phase)
                <x-ui.card :title="'Phase '.$phase->position.' · '.$phase->type->label()" :icon="$phase->type->icon()">
                    <x-slot:description>
                        <span class="mt-1.5 flex flex-wrap gap-1.5">
                            <x-ui.badge :value="$phase->status" />
                            <x-ui.badge tone="gray" :dot="false" :icon="$phase->effectiveMode()->icon()">{{ $phase->effectiveMode()->label() }}</x-ui.badge>
                            <x-ui.badge tone="gray" :dot="false" icon="scale">{{ $phase->rules->voteMode->label() }}@if ($phase->rules->voteMode === VoteMode::Mixed) · {{ $phase->rules->juryWeight }}/{{ $phase->rules->publicWeight }}@endif</x-ui.badge>
                            <x-ui.badge tone="gray" :dot="false" icon="clock">{{ $phase->rules->rounds }} × {{ $phase->rules->turnDuration }} s</x-ui.badge>
                            @if ($phase->type === PhaseType::Groups)<x-ui.badge tone="gray" :dot="false" icon="squares-2x2">{{ $phase->rules->groupCount }} poules · top {{ $phase->qualifiers_per_group }}</x-ui.badge>@endif
                            @if ($phase->rules->grandFinalReset)<x-ui.badge tone="gray" :dot="false" icon="arrow-path">Finale reset</x-ui.badge>@endif
                            @if ($phase->effectiveMode() === CompetitionMode::Online)
                                <x-ui.badge tone="gray" :dot="false" icon="film">{{ collect($phase->rules->mediaTypes)->map->label()->implode(' / ') }} · {{ gmdate('i:s', $phase->rules->mediaMaxDuration) }} max · {{ $phase->rules->mediaMaxSizeMb }} Mo</x-ui.badge>
                            @endif
                        </span>
                    </x-slot:description>
                    @if (! $phase->isFrozen() && $canUpdate)
                        <x-slot:actions>
                            <x-ui.confirm :action="route('organizers.competitions.phases.destroy', [$organizer, $competition, $phase])" method="DELETE" title="Supprimer cette phase ?" confirm="Supprimer">
                                <x-ui.button size="sm" variant="ghost" icon="trash" class="!text-rose-600"><span class="sr-only">Supprimer</span></x-ui.button>
                            </x-ui.confirm>
                            <x-ui.button size="sm" variant="secondary" icon="pencil-square" x-data x-on:click="$dispatch('open-modal', 'edit-phase-{{ $phase->id }}')">Modifier</x-ui.button>
                            @php
                                $before = $competition->phases->where('position', '<', $phase->position)->sortBy('position')->last();
                            @endphp
                            @if ($before === null || $before->status === \App\Enums\PhaseStatus::Finished)
                                <x-ui.confirm :action="route('organizers.competitions.phases.start', [$organizer, $competition, $phase])" :danger="false" icon="rocket-launch"
                                    title="Démarrer la phase ?" message="Les règles seront figées et les poules ou le bracket générés automatiquement." confirm="Démarrer">
                                    <x-ui.button size="sm" variant="primary" icon="rocket-launch">Démarrer</x-ui.button>
                                </x-ui.confirm>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 text-xs text-slate-500"><x-ui.icon name="clock" variant="m" class="size-4" /> Après la phase {{ $before->position }}</span>
                            @endif
                        </x-slot:actions>
                    @endif

                    @if ($phase->isFrozen() && $phase->stages->isNotEmpty())
                        <div class="mb-6 space-y-3">
                            @foreach ($phase->stages as $stage)
                                <x-bo.stage-panel :stage="$stage" :organizer="$organizer" :competition="$competition" :can-run="$canRun" />
                            @endforeach
                        </div>
                    @endif

                    @if (! $phase->isFrozen())
                        <x-bo.phase-calendar :phase="$phase" :organizer="$organizer" :competition="$competition" :can-update="$canUpdate" />
                    @elseif ($phase->type === PhaseType::Groups)
                        @php
                            $groupMatches = $phase->matches->whereNotNull('group_id')->sortBy('bracket_position');
                            $openGroups = $groupMatches->whereNotIn('status', [\App\Enums\MatchStatus::Closed, \App\Enums\MatchStatus::Cancelled])->count();
                        @endphp
                        @if ($phase->results_published_at)
                            <div class="mb-4 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200">
                                <x-ui.icon name="check-badge" class="size-5 shrink-0" /> Résultats publiés le {{ $phase->results_published_at->translatedFormat('d F à H:i') }} : les qualifiés passent à la phase suivante.
                            </div>
                        @elseif ($phase->status === \App\Enums\PhaseStatus::InProgress)
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-900/5 dark:bg-white/[0.03] dark:ring-white/10">
                                <p class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                                    <x-ui.icon name="megaphone" class="mt-0.5 size-5 shrink-0 text-brand-600" />
                                    <span>{{ $openGroups
                                        ? "Chaque artiste présente sa prestation, le public vote (1 vote par phase) et le jury délibère. Publication possible quand les {$openGroups} poule(s) restante(s) seront closes."
                                        : 'Toutes les poules sont closes : vérifiez les classements puis publiez les résultats.' }}</span>
                                </p>
                                @if ($canRun && ! $openGroups)
                                    <x-ui.confirm :action="route('organizers.competitions.phases.publish', [$organizer, $competition, $phase])" :danger="false" icon="megaphone"
                                        title="Publier les résultats des poules ?" message="Les {{ $phase->qualifiers_per_group }} premier(s) de chaque poule se qualifient, les autres sont éliminés. Le classement devient public et les artistes sont prévenus." confirm="Publier">
                                        <x-ui.button size="sm" icon="megaphone">Publier les résultats</x-ui.button>
                                    </x-ui.confirm>
                                @endif
                            </div>
                        @endif
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($groupMatches as $match)
                                <x-bo.group-card :match="$match" :phase="$phase" :organizer="$organizer" :competition="$competition" :can-run="$canRun" :onsite="$phase->effectiveMode() === CompetitionMode::OnSite" />
                            @endforeach
                        </div>
                    @else
                        <x-bo.bracket :phase="$phase" :organizer="$organizer" :competition="$competition" :can-run="$canRun" />
                    @endif
                </x-ui.card>
            @empty
                <x-ui.empty icon="trophy" title="Aucune phase" description="Une compétition enchaîne des phases : poules, élimination simple ou double élimination.">
                    @if ($canUpdate && $canAddPhase)<x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-phase')">Ajouter une phase</x-ui.button>@endif
                </x-ui.empty>
            @endforelse
            </div>
        </x-ui.tab-panel>

        {{-- Pre-selection --}}
        <x-ui.tab-panel name="preselection">
            <div data-live="tab-preselection">
            <x-bo.preselection-panel :competition="$competition" :organizer="$organizer" :can-update="$canUpdate" :can-run="$canRun" />
            </div>
        </x-ui.tab-panel>

        {{-- Participants --}}
        <x-ui.tab-panel name="participants">
            <div data-live="tab-participants">
            <x-ui.card title="Participants" description="Validez les inscriptions et attribuez les têtes de série" icon="users"
                x-data="pager({ search: '', status: '' })">
                <x-slot:actions>
                    <div class="relative">
                        <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" x-model="search" placeholder="Rechercher…" class="w-44 rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                    </div>
                    <select x-model="status" class="rounded-lg border-0 bg-slate-50 py-1.5 pr-8 pl-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                        <option value="">Tous les statuts</option>
                        @foreach (ParticipantStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                    </select>
                </x-slot:actions>

                @if ($participants->isEmpty())
                    <x-ui.empty icon="user-plus" title="Aucune inscription" description="Les artistes s'inscrivent depuis l'application mobile une fois les inscriptions ouvertes." />
                @else
                    <x-ui.table>
                        <x-slot:head><th>Artiste</th><th>Compte</th><th>Statut</th><th>Paiement</th><th>Inscrit le</th><th class="!text-right">Seed & statut</th></x-slot:head>
                        @foreach ($participants as $participant)
                            <tr x-effect="track({{ $loop->index }}, (! status || status === @js($participant->status->value)) && @js(Str::lower($participant->stage_name.' '.$participant->user->name.' '.$participant->user->phone)).includes(search.toLowerCase()))" x-show="visible({{ $loop->index }})">
                                <td>
                                    <x-bo.participant-card :participant="$participant" :competition="$competition" />
                                </td>
                                <td><p>{{ $participant->user->name }}</p><p class="text-xs text-slate-500">{{ $participant->user->phone }}</p></td>
                                <td><x-ui.badge :value="$participant->status" /></td>
                                <td>
                                    @php $paid = $participant->payments->firstWhere('status', \App\Enums\PaymentStatus::Paid); @endphp
                                    @if ($paid)
                                        <x-ui.badge tone="green" :dot="false" icon="check">{{ number_format($paid->amount, 0, ',', ' ') }} {{ $paid->currency }}</x-ui.badge>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ $paid->method->label() }} · {{ $paid->reference }}</p>
                                    @elseif ($competition->requiresPayment())
                                        <x-ui.badge tone="amber">En attente</x-ui.badge>
                                    @else
                                        <span class="text-xs text-slate-400">Gratuit</span>
                                    @endif
                                </td>
                                <td class="text-slate-500">{{ $participant->created_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    @if ($canRegistrations)
                                        <form method="POST" action="{{ route('organizers.competitions.participants.update', [$organizer, $competition, $participant]) }}" class="flex items-center justify-end gap-2">
                                            @csrf @method('PATCH')
                                            <input type="number" name="seed" value="{{ $participant->seed }}" min="1" placeholder="#" title="Tête de série"
                                                class="w-16 rounded-lg border-0 bg-slate-50 py-1.5 text-center text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                                            <select name="status" class="w-48 rounded-lg border-0 bg-slate-50 py-1.5 pr-8 pl-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                                                @unless (in_array($participant->status, $editableStatuses, true))
                                                    <option value="" selected>{{ $participant->status->label() }} (inchangé)</option>
                                                @endunless
                                                @foreach ($editableStatuses as $status)
                                                    <option value="{{ $status->value }}" @selected($participant->status === $status)>{{ $status->label() }}</option>
                                                @endforeach
                                            </select>
                                            <x-ui.button type="submit" size="sm" variant="soft" icon="check"><span class="sr-only">Enregistrer</span></x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                    <x-ui.pager :count="$participants->count()" class="-mx-5 -mb-5 mt-4" />
                    <p x-show="! total" x-cloak class="py-6 text-center text-sm text-slate-500">Aucun participant ne correspond à ces filtres.</p>
                @endif
            </x-ui.card>
            </div>
        </x-ui.tab-panel>

        {{-- Jury & criteria --}}
        <x-ui.tab-panel name="jury">
            <div data-live="tab-jury">
            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card title="Jury" description="Les jurés notent depuis l'application mobile" icon="scale">
                    @if ($canUpdate)
                        <x-slot:actions><x-ui.button size="sm" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'invite-judge')">Ajouter un juré</x-ui.button></x-slot:actions>
                    @endif
                    @forelse ($competition->judges as $judge)
                        <div class="flex items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                            <x-ui.avatar :name="$judge->user->name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $judge->user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $judge->user->phone }}</p>
                            </div>
                            <x-ui.badge :value="$judge->status" />
                            @if ($canUpdate)
                                <x-ui.confirm :action="route('organizers.competitions.judges.destroy', [$organizer, $competition, $judge])" method="DELETE" :title="'Retirer '.$judge->user->name.' du jury ?'" confirm="Retirer">
                                    <x-ui.button size="xs" variant="ghost" icon="x-mark"><span class="sr-only">Retirer</span></x-ui.button>
                                </x-ui.confirm>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty icon="scale" title="Aucun juré" description="Créez vos jurés avec leur nom et leur numéro : ils ne verront que cette compétition." />
                    @endforelse
                </x-ui.card>

                <x-ui.card title="Critères de notation" description="Chaque note est ramenée sur 100 puis pondérée" icon="adjustments-horizontal">
                    @if ($canUpdate)
                        <x-slot:actions><x-ui.button size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-criterion')">Ajouter</x-ui.button></x-slot:actions>
                    @endif
                    @php $totalWeight = $competition->criteria->sum('weight') ?: 1; @endphp
                    @forelse ($competition->criteria as $criterion)
                        <div class="border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $criterion->name }}</p>
                                <div class="flex items-center gap-2">
                                    <x-ui.badge tone="gray" :dot="false">sur {{ $criterion->max_points }}</x-ui.badge>
                                    <x-ui.badge tone="violet" :dot="false">{{ round($criterion->weight / $totalWeight * 100) }} %</x-ui.badge>
                                    @if ($canUpdate)
                                        <x-ui.confirm :action="route('organizers.competitions.criteria.destroy', [$organizer, $competition, $criterion])" method="DELETE" :title="'Supprimer « '.$criterion->name.' » ?'" confirm="Supprimer">
                                            <x-ui.button size="xs" variant="ghost" icon="trash"><span class="sr-only">Supprimer</span></x-ui.button>
                                        </x-ui.confirm>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-brand-600" style="width: {{ $criterion->weight / $totalWeight * 100 }}%"></div></div>
                        </div>
                    @empty
                        <x-ui.empty icon="adjustments-horizontal" title="Aucun critère" description="Ex. Flow, Lyrics, Présence scénique, Technique." />
                    @endforelse
                </x-ui.card>
            </div>
            </div>
        </x-ui.tab-panel>

        {{-- Settings --}}
        <x-ui.tab-panel name="settings">
            <form method="POST" action="{{ route('organizers.competitions.update', [$organizer, $competition]) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                @csrf @method('PUT')
                <x-ui.card title="Informations générales" icon="pencil-square" class="xl:col-span-2">
                    <fieldset @disabled(! $canUpdate) class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="name" label="Nom" :value="$competition->name" required class="sm:col-span-2" />
                        <x-ui.select name="discipline" label="Discipline" :options="Discipline::options()" :value="$competition->discipline" />
                        <x-ui.select name="mode" label="Mode" :options="CompetitionMode::options()" :value="$competition->mode" hint="Chaque phase peut surcharger ce mode." />
                        <x-ui.input name="registration_ends_at" type="datetime-local" label="Fin des inscriptions" :value="$competition->registration_ends_at" />
                        <x-ui.input name="max_participants" type="number" min="2" label="Participants max." :value="$competition->max_participants" />
                        <x-ui.input name="entry_fee" type="number" min="0" label="Frais d'inscription" :value="$competition->entry_fee" suffix="XOF" />
                        <x-location-select :city="$competition->city_id" :commune="$competition->commune_id" label="Lieu (ville)" hint="Où se déroule la compétition (présentiel)." class="sm:col-span-2" />
                    </fieldset>
                </x-ui.card>

                <x-ui.card title="Options" icon="adjustments-vertical">
                    <fieldset @disabled(! $canUpdate) class="-mx-3 space-y-1">
                        <x-ui.toggle name="settings[registration_requires_approval]" label="Valider les inscriptions" description="Chaque artiste doit être validé avant de concourir." :checked="$settings->registrationRequiresApproval" />
                        <x-ui.toggle name="settings[public_voting_enabled]" label="Vote du public" description="Les spectateurs votent depuis l'application." :checked="$settings->publicVotingEnabled" />
                        <x-ui.toggle name="settings[show_live_results]" label="Résultats en direct" description="Afficher les scores avant la clôture du vote." :checked="$settings->showLiveResults" />
                        <x-ui.toggle name="settings[submissions_require_approval]" label="Valider les soumissions" description="En ligne : chaque vidéo ou son est validé avant d'être visible du public et du jury." :checked="$settings->submissionsRequireApproval" />
                        <x-ui.toggle name="settings[onsite_vote_code]" label="Code de salle en présentiel" description="Seules les personnes présentes (code affiché à l'écran) peuvent voter." :checked="$settings->onsiteVoteCode" />
                        <div class="px-3 pt-2">
                            <x-ui.input name="settings[max_votes_per_device]" type="number" min="1" label="Votes max. par appareil" :value="$settings->maxVotesPerDevice" hint="Anti-fraude. Vide = illimité." />
                        </div>
                        <div class="px-3 pt-2">
                            <x-ui.input name="settings[timezone]" label="Fuseau horaire" :value="$settings->timezone" icon="globe-alt" />
                        </div>
                    </fieldset>
                </x-ui.card>

                <x-ui.card title="Présentation" icon="document-text" class="xl:col-span-2" description="Ce que les artistes et le public liront avant de s'inscrire : l'esprit, le déroulé, les règles.">
                    <fieldset @disabled(! $canUpdate) class="min-w-0">
                        <x-ui.rich-editor name="description" :value="$competition->description" placeholder="Présentez votre compétition : concept, déroulé, règles, lieu…" hint="Gras, italique, titres, listes, citations et liens. Obligatoire pour ouvrir les inscriptions." />
                    </fieldset>
                </x-ui.card>

                @php $prizeRows = old('prizes', $competition->prizeList() ?: [['rank' => '1er prix', 'reward' => '']]); @endphp
                <x-ui.card title="Récompenses" icon="gift" description="Dans l'ordre du classement.">
                    <fieldset @disabled(! $canUpdate) x-data="{ prizes: @js(array_values($prizeRows)) }" class="space-y-3">
                        <template x-for="(prize, index) in prizes" :key="index">
                            <div class="flex items-start gap-2 rounded-xl bg-slate-50 p-2.5 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                <span class="mt-1.5 grid size-7 shrink-0 place-items-center rounded-lg bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" x-text="index + 1"></span>
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <input type="text" :name="`prizes[${index}][rank]`" x-model="prize.rank" maxlength="60" placeholder="Rang (ex. 1er prix)"
                                        class="block w-full rounded-lg border-0 bg-white py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10">
                                    <input type="text" :name="`prizes[${index}][reward]`" x-model="prize.reward" maxlength="255" placeholder="Ex. 500 000 XOF + clip vidéo"
                                        class="block w-full rounded-lg border-0 bg-white py-2 text-sm text-slate-900 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:text-white dark:ring-white/10">
                                </div>
                                <button type="button" x-on:click="prizes.splice(index, 1)" x-show="prizes.length > 1" class="mt-1.5 rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Retirer">
                                    <x-ui.icon name="trash" variant="m" class="size-4" />
                                </button>
                            </div>
                        </template>
                        <button type="button" x-on:click="prizes.push({ rank: `${prizes.length + 1}e prix`, reward: '' })" x-show="prizes.length < 20"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700 dark:border-white/15 dark:text-slate-300">
                            <x-ui.icon name="plus" variant="m" class="size-4" /> Ajouter une récompense
                        </button>
                        @error('prizes')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    </fieldset>
                </x-ui.card>

                <x-ui.card title="Règlement" icon="scale" class="xl:col-span-2" description="Les règles que les artistes acceptent en s'inscrivant, affichées sur la page de la compétition.">
                    @if ($canUpdate)
                        <x-slot:actions>
                            <x-ui.confirm :action="route('organizers.competitions.guide.draft', [$organizer, $competition])" :danger="false" icon="sparkles"
                                title="Générer un brouillon ?" message="Le déroulé et le règlement sont pré-remplis à partir de vos phases, de la présélection et des critères. Rien n'est enregistré : relisez, modifiez puis enregistrez. Ce que vous avez déjà écrit dans ces deux champs sera remplacé dans le formulaire." confirm="Générer">
                                <x-ui.button size="sm" variant="secondary" icon="sparkles">Générer un brouillon</x-ui.button>
                            </x-ui.confirm>
                        </x-slot:actions>
                    @endif
                    <fieldset @disabled(! $canUpdate) class="min-w-0">
                        <x-ui.rich-editor name="regulations" :value="$competition->regulations" placeholder="Participation, prestations, votes, départage, conduite…" hint="Écrit par vous. « Générer un brouillon » le pré-remplit à partir de votre configuration." />
                    </fieldset>
                </x-ui.card>

                @php $scheduleRows = old('schedule', $competition->scheduleList() ?: [['title' => '', 'date' => null, 'details' => '']]); @endphp
                <x-ui.card title="Déroulé" icon="calendar-days" description="Les grandes étapes, dans l'ordre.">
                    <fieldset @disabled(! $canUpdate) x-data="{ steps: @js(array_values($scheduleRows)) }" class="space-y-3">
                        <template x-for="(step, index) in steps" :key="index">
                            <div class="flex items-start gap-2 rounded-xl bg-slate-50 p-2.5 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                <span class="mt-1.5 grid size-7 shrink-0 place-items-center rounded-lg bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300" x-text="index + 1"></span>
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <input type="text" :name="`schedule[${index}][title]`" x-model="step.title" maxlength="100" placeholder="Étape (ex. Finale sur scène)"
                                        class="block w-full rounded-lg border-0 bg-white py-2 text-sm font-semibold text-slate-900 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:text-white dark:ring-white/10">
                                    <input type="datetime-local" :name="`schedule[${index}][date]`" x-model="step.date"
                                        class="block w-full rounded-lg border-0 bg-white py-1.5 text-xs text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10">
                                    <input type="text" :name="`schedule[${index}][details]`" x-model="step.details" maxlength="255" placeholder="Précisions (facultatif)"
                                        class="block w-full rounded-lg border-0 bg-white py-1.5 text-xs text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10">
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <button type="button" x-on:click="index > 0 && steps.splice(index - 1, 0, steps.splice(index, 1)[0])" x-show="index > 0" class="rounded-lg p-1.5 text-slate-400 hover:bg-white hover:text-brand-600 dark:hover:bg-white/10" title="Monter"><x-ui.icon name="chevron-up" variant="m" class="size-4" /></button>
                                    <button type="button" x-on:click="steps.splice(index, 1)" x-show="steps.length > 1" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Retirer"><x-ui.icon name="trash" variant="m" class="size-4" /></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" x-on:click="steps.push({ title: '', date: null, details: '' })" x-show="steps.length < 30"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700 dark:border-white/15 dark:text-slate-300">
                            <x-ui.icon name="plus" variant="m" class="size-4" /> Ajouter une étape
                        </button>
                        @error('schedule.*')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    </fieldset>
                </x-ui.card>

                @if ($canUpdate)
                    <div class="flex justify-end xl:col-span-3">
                        <x-ui.button type="submit" variant="primary" icon="check">Enregistrer les modifications</x-ui.button>
                    </div>
                @endif
            </form>
        </x-ui.tab-panel>
    </x-ui.tabs>

    {{-- Create / edit phases --}}
    @if ($canUpdate)
        @if ($canAddPhase)
            <x-bo.phase-form :competition="$competition" :organizer="$organizer" />
        @endif
        @foreach ($competition->phases->reject->isFrozen() as $editable)
            <x-bo.phase-form :competition="$competition" :organizer="$organizer" :phase="$editable"
                :types="$competition->phases->contains(fn ($p) => $p->position > $editable->position) ? [PhaseType::Groups] : PhaseType::cases()" />
        @endforeach

        <x-ui.modal name="invite-judge" title="Ajouter un juré" description="Si le numéro n'a pas encore de compte, il est créé et le juré reçoit un mot de passe provisoire par SMS. Il notera depuis l'application." icon="scale" :show="$errors->hasAny(['phone', 'country_id'])">
            <form method="POST" action="{{ route('organizers.competitions.judges.store', [$organizer, $competition]) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="invite-judge">
                <x-ui.input name="name" label="Nom du juré" icon="user" required />
                <x-phone-input />
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'invite-judge')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="user-plus">Créer le juré</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="add-criterion" title="Nouveau critère" icon="adjustments-horizontal" :show="$errors->hasAny(['max_points', 'weight'])">
            <form method="POST" action="{{ route('organizers.competitions.criteria.store', [$organizer, $competition]) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_form" value="add-criterion">
                <x-ui.input name="name" label="Nom" placeholder="Flow" required class="sm:col-span-2" />
                <x-ui.input name="max_points" type="number" min="1" max="100" label="Note maximale" value="10" required />
                <x-ui.input name="weight" type="number" step="0.5" min="0.5" label="Poids" value="1" required hint="Importance relative." />
                <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'add-criterion')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="plus">Ajouter</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::backOffice($competition->id)]" />
</x-layouts.app>
