@php
    use App\Enums\PreselectionState;

    $state = $preselection->state();
    $canScore = $preselection->acceptsScores();
    $percent = $total ? round($scored / $total * 100) : 0;
    $tabUrl = fn (string $tab) => route('jury.competitions.preselection', array_filter([$competition, 'onglet' => $tab === 'notees' ? 'notees' : null, 'q' => $search ?: null]));
@endphp

<x-layouts.portal :title="'Présélection · '.$competition->name">
    <x-ui.page-header title="Présélection" :breadcrumbs="['Mes compétitions' => route('jury.dashboard'), $competition->name => route('jury.competitions.show', $competition), 'Présélection' => null]">
        <x-slot:description>
            <x-ui.badge :value="$state" />
            <span>Jury {{ $preselection->effectiveWeights()['jury'] }} % du score</span>
            @if ($preselection->splitsJudging())<span>· Prestations réparties entre les jurés</span>@endif
        </x-slot:description>
    </x-ui.page-header>

    {{-- Progress and next entry --}}
    <div class="mb-6 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-slate-900/60 dark:ring-white/10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Ta progression</p>
                <p class="mt-1 font-display text-3xl font-extrabold tabular-nums">{{ $scored }} <span class="text-lg font-bold text-slate-400">/ {{ $total }} notées</span></p>
            </div>
            @if ($canScore && $next)
                <x-ui.button size="lg" icon-right="arrow-right" :href="route('jury.competitions.preselection.entries.show', [$competition, $next])">{{ $scored ? 'Continuer la notation' : 'Commencer la notation' }}</x-ui.button>
            @elseif ($total && ! $next)
                <x-ui.badge tone="green" icon="check-badge" :dot="false">Tout est noté, merci !</x-ui.badge>
            @endif
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $percent }}%"></div></div>
        <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
            @if ($canScore)
                <x-ui.icon name="clock" variant="m" class="size-4" />
                <span x-data="countdown('{{ $preselection->deliberationEndsAt()->toIso8601String() }}')">Fin de la délibération dans <strong class="tabular-nums" x-text="label">{{ $preselection->deliberationEndsAt()->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</strong> · tes notes sont définitives une fois enregistrées.</span>
            @else
                <x-ui.icon name="lock-closed" variant="m" class="size-4" />
                {{ $state === PreselectionState::Published ? 'La sélection est publiée : notes en lecture seule.' : 'La délibération est close : notes en lecture seule.' }}
            @endif
        </p>
    </div>

    {{-- Tabs + search --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">
            @foreach (['a_noter' => ['À noter', $total - $scored], 'notees' => ['Notées', $scored]] as $key => [$label, $count])
                <a href="{{ $tabUrl($key) }}" @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition',
                    'bg-white text-slate-900 shadow-soft dark:bg-white/10 dark:text-white' => $tab === $key,
                    'text-slate-500 hover:text-slate-800 dark:text-slate-400' => $tab !== $key,
                ])>{{ $label }} <span class="rounded-full bg-slate-500/10 px-1.5 text-xs tabular-nums">{{ $count }}</span></a>
            @endforeach
        </nav>
        <form method="GET" class="relative w-full sm:w-64">
            @if ($tab === 'notees')<input type="hidden" name="onglet" value="notees">@endif
            <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher un artiste…" class="w-full rounded-xl border-0 bg-white py-2 pr-3 pl-9 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
        </form>
    </div>

    @if ($entries->isEmpty())
        <x-ui.empty icon="film" :title="$tab === 'notees' ? 'Aucune prestation notée' : ($total ? 'Rien à noter ici' : 'Aucune prestation à noter')"
            :description="$search ? 'Aucun artiste ne correspond à la recherche.' : 'Les prestations validées par l\'organisateur apparaissent ici.'" />
    @else
        {{-- Compact list: no player here, one entry at a time on its page --}}
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 dark:divide-white/5 dark:bg-slate-900/60 dark:ring-white/10">
            @foreach ($entries as $entry)
                @php
                    $mine = $myScores->get($entry->id);
                    $total100 = $mine ? $mine->sum('score') : null;
                @endphp
                <li>
                    <a href="{{ route('jury.competitions.preselection.entries.show', [$competition, $entry]) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                        <x-ui.avatar :name="$entry->participant->stage_name" :src="$entry->participant->user?->avatarUrl()" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $entry->participant->stage_name }}</span>
                            <span class="block text-xs text-slate-500">{{ $entry->media_type?->label() }}@if ($entry->duration_seconds) · {{ gmdate('i:s', $entry->duration_seconds) }}@endif</span>
                        </span>
                        @if ($mine)
                            <x-ui.badge tone="green" icon="check" :dot="false">{{ rtrim(rtrim(number_format($total100, 1, ',', ''), '0'), ',') }} pts</x-ui.badge>
                        @else
                            <x-ui.badge tone="amber">À noter</x-ui.badge>
                        @endif
                        <x-ui.icon name="chevron-right" variant="m" class="size-5 text-slate-300" />
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
</x-layouts.portal>
