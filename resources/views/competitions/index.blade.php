@php
    use App\Enums\CompetitionMode;
    use App\Enums\CompetitionStatus;
    use App\Enums\Discipline;

    $user = auth()->user();
    $canCreate = $user->can('create', [\App\Models\Competition::class, $organizer]);
    $status = $filters['status'] ?? null;
    $filtered = collect($filters)->except(['sort', 'status'])->filter()->isNotEmpty();
    $fee = fn ($c) => $c->entry_fee ? number_format($c->entry_fee, 0, ',', ' ').' '.$c->currency : 'Gratuit';
    $select = 'rounded-xl border-0 bg-white py-2 pr-8 pl-3 text-sm shadow-soft ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10';
@endphp

<x-layouts.app title="Compétitions">
    <x-ui.page-header title="Compétitions" :description="$organizer->name.' · créez, modifiez, dupliquez ou supprimez vos compétitions.'"
        :breadcrumbs="['Tableau de bord' => route('dashboard'), $organizer->name => route('organizers.show', $organizer), 'Compétitions' => null]">
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button variant="primary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-competition')">Nouvelle compétition</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div data-live="competitions">
    {{-- Status tabs --}}
    <nav class="-mx-4 mb-4 flex gap-1 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:px-0" aria-label="Statuts">
        @foreach (['' => 'Toutes'] + CompetitionStatus::options() as $value => $label)
            @php
                $active = (string) $status === (string) $value;
            @endphp
            <a href="{{ route('organizers.competitions.index', [$organizer, ...array_filter([...$filters, 'status' => $value])]) }}" @class([
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 transition',
                'bg-slate-900 text-white ring-slate-900 dark:bg-white dark:text-slate-900 dark:ring-white' => $active,
                'bg-white text-slate-600 ring-slate-200 hover:text-slate-900 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10 dark:hover:text-white' => ! $active,
            ])>
                {{ $label }}
                <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20' => $active, 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400' => ! $active])>{{ $value === '' ? $counts->sum() : ($counts[$value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Filters --}}
    <form method="GET" class="mb-6 grid grid-cols-2 gap-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto]">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="relative col-span-2 lg:col-span-1">
            <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher une compétition…"
                class="w-full rounded-xl border-0 bg-white py-2 pr-3 pl-9 text-sm shadow-soft ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
        </div>
        <select name="discipline" onchange="this.form.submit()" class="{{ $select }}" aria-label="Discipline">
            <option value="">Toutes disciplines</option>
            @foreach (Discipline::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['discipline'] ?? null) === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="mode" onchange="this.form.submit()" class="{{ $select }}" aria-label="Mode">
            <option value="">Tous modes</option>
            @foreach (CompetitionMode::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['mode'] ?? null) === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="sort" onchange="this.form.submit()" class="{{ $select }}" aria-label="Tri">
            @foreach ($sorts as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <x-ui.button type="submit" variant="secondary" icon="funnel" class="flex-1 lg:flex-none">Filtrer</x-ui.button>
            @if ($filtered || $status)
                <x-ui.button variant="ghost" :href="route('organizers.competitions.index', $organizer)" icon="x-mark">Effacer</x-ui.button>
            @endif
        </div>
    </form>

    @if ($competitions->isEmpty())
        <x-ui.empty icon="trophy" :title="$filtered || $status ? 'Aucun résultat' : 'Aucune compétition'"
            :description="$filtered || $status ? 'Modifiez ou effacez les filtres.' : 'Créez votre première compétition : elle démarre en brouillon.'">
            @if ($canCreate && ! $filtered && ! $status)
                <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-competition')">Créer une compétition</x-ui.button>
            @endif
        </x-ui.empty>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10">
            <div class="hidden grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,1fr)_3rem] gap-4 border-b border-slate-100 px-5 py-3 text-xs font-semibold tracking-wide text-slate-400 uppercase lg:grid dark:border-white/5">
                <span>Compétition</span><span>Statut</span><span>Inscrits</span><span>Inscriptions · frais</span><span></span>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($competitions as $competition)
                    @php
                        $max = $competition->max_participants;
                        $count = $competition->participants_count;
                        $canUpdate = $user->can('update', $competition);
                        $canDelete = $user->can('delete', $competition);
                        $show = route('organizers.competitions.show', [$organizer, $competition]);
                    @endphp
                    <li class="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 px-4 py-4 transition hover:bg-slate-50/70 sm:px-5 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,1fr)_3rem] lg:gap-4 dark:hover:bg-white/[0.02]">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20"><x-ui.icon :name="$competition->discipline->icon()" class="size-5" /></span>
                            <div class="min-w-0">
                                <a href="{{ $show }}" class="block truncate font-semibold text-slate-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-300">{{ $competition->name }}</a>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2.5 text-xs text-slate-500">
                                    <span>{{ $competition->discipline->label() }}</span>
                                    <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-3.5" />{{ $competition->mode->label() }}</span>
                                    <span class="hidden sm:inline">Créée le {{ $competition->created_at->translatedFormat('d M Y') }}</span>
                                </p>
                            </div>
                        </div>

                        {{-- Actions (top-right on mobile, last column on desktop) --}}
                        <div class="col-start-2 row-start-1 flex justify-end lg:col-start-5">
                            <x-ui.dropdown>
                                <x-slot:trigger><x-ui.button variant="ghost" size="sm" icon="ellipsis-vertical"><span class="sr-only">Actions pour {{ $competition->name }}</span></x-ui.button></x-slot:trigger>
                                <x-ui.dropdown-item :href="$show" icon="eye">Ouvrir</x-ui.dropdown-item>
                                @if ($canUpdate)
                                    <x-ui.dropdown-item :href="$show.'#settings'" icon="pencil-square">Modifier</x-ui.dropdown-item>
                                @endif
                                @if ($canCreate)
                                    <x-ui.confirm :action="route('organizers.competitions.duplicate', [$organizer, $competition])" :danger="false" icon="document-duplicate"
                                        title="Dupliquer la compétition ?" message="Une copie en brouillon est créée avec la présentation, les récompenses, les options, les critères et les phases. Les participants, jurés, matchs et votes ne sont pas copiés." confirm="Dupliquer">
                                        <x-ui.dropdown-item icon="document-duplicate">Dupliquer</x-ui.dropdown-item>
                                    </x-ui.confirm>
                                @endif
                                @if ($canDelete)
                                    <x-ui.confirm :action="route('organizers.competitions.destroy', [$organizer, $competition])" method="DELETE" :title="'Supprimer « '.$competition->name.' » ?'" message="Elle disparaîtra du back-office et de l'application." confirm="Supprimer">
                                        <x-ui.dropdown-item icon="trash" danger>Supprimer</x-ui.dropdown-item>
                                    </x-ui.confirm>
                                @elseif ($canUpdate)
                                    <p class="px-2.5 py-2 text-xs text-slate-400">Suppression : brouillon ou compétition annulée uniquement.</p>
                                @endif
                            </x-ui.dropdown>
                        </div>

                        <div class="col-span-2 flex flex-wrap items-center gap-2 lg:col-span-1 lg:col-start-2 lg:row-start-1">
                            <x-ui.badge :value="$competition->status" />
                            @if (blank($competition->description) || $competition->prizeList() === [])
                                @if ($competition->status === CompetitionStatus::Draft)<x-ui.badge tone="amber" icon="exclamation-triangle" :dot="false">À compléter</x-ui.badge>@endif
                            @endif
                        </div>

                        <div class="col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-1">
                            <div class="flex justify-between text-xs"><span class="text-slate-500 lg:hidden">Inscrits</span><span class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ $count }}{{ $max ? ' / '.$max : '' }}</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $max ? min(100, round($count / $max * 100)) : min(100, $count * 5) }}%"></div>
                            </div>
                        </div>

                        <div class="col-span-2 flex justify-between text-xs text-slate-500 lg:col-span-1 lg:col-start-4 lg:row-start-1 lg:block">
                            <p class="inline-flex items-center gap-1"><x-ui.icon name="calendar" variant="m" class="size-3.5" />{{ $competition->registration_ends_at?->translatedFormat('d M Y, H:i') ?? 'Sans date' }}</p>
                            <p class="font-medium text-slate-700 lg:mt-0.5 dark:text-slate-300">{{ $fee($competition) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($competitions->hasPages())
            <div class="mt-6">{{ $competitions->links() }}</div>
        @endif
    @endif

    </div>

    @if ($canCreate)
        <x-bo.create-competition :organizer="$organizer" />
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::organizer($organizer->id)]" />
</x-layouts.app>
