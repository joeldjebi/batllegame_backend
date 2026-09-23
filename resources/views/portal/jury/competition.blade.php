@php use App\Enums\MatchStatus; @endphp

<x-layouts.portal :title="$competition->name">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Mes compétitions' => route('jury.dashboard'), $competition->name => null]">
        <x-slot:description>
            <x-ui.badge :value="$competition->status" />
            <span>{{ $competition->organizer->name }}</span>
            <span>{{ $criteriaCount }} critère(s) de notation</span>
        </x-slot:description>
    </x-ui.page-header>

    @if ($competition->preselection)
        @php($pstate = $competition->preselection->state())
        <a href="{{ route('jury.competitions.preselection', $competition) }}" class="mb-6 flex items-center justify-between gap-4 rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 transition hover:ring-brand-300 dark:bg-slate-900/60 dark:ring-white/10">
            <span class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="funnel" class="size-5" /></span>
                <span><span class="block font-semibold">Présélection</span><span class="text-sm text-slate-500">Notez les prestations des artistes candidats</span></span>
            </span>
            <x-ui.badge :value="$pstate" />
        </a>
    @endif

    @if ($matches->isEmpty())
        <x-ui.empty icon="clock" title="Aucun match à noter pour l'instant" description="Les matchs apparaissent ici dès que l'organisateur ouvre le vote." />
    @else
        <x-ui.card :padding="false">
            <div class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($matches as $match)
                    @php($done = collect($scored->get($match->id, []))->count())
                    <a href="{{ route('jury.competitions.matches.show', [$competition, $match]) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $match->stage?->name ?? 'Match' }} · {{ $match->phase->effectiveMode()->label() }}</p>
                            <p class="mt-1 font-semibold">{{ $match->slots->map(fn ($s) => $s->participant?->stage_name ?? '—')->implode(' vs ') }}</p>
                        </div>
                        <x-ui.badge :value="$match->status" />
                        @if ($match->status === MatchStatus::Voting)
                            <x-ui.badge :tone="$done >= 2 ? 'green' : 'amber'" :dot="false">{{ $done }}/2 noté(s)</x-ui.badge>
                        @endif
                        <x-ui.icon name="chevron-right" variant="m" class="size-5 text-slate-300" />
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</x-layouts.portal>
