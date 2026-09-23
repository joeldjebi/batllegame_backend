@php
    use App\Enums\CompetitionStatus;
    use App\Enums\Discipline;

    $status = request('status');
    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
@endphp

<x-layouts.app title="Compétitions">
    <x-ui.page-header title="Compétitions" description="Toutes les compétitions de la plateforme, tous organisateurs confondus." :breadcrumbs="['Console' => route('admin.dashboard'), 'Compétitions' => null]" />

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-white/5">
            <nav class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">
                @foreach (['' => 'Toutes'] + CompetitionStatus::options() as $value => $label)
                    <a href="{{ route('admin.competitions.index', array_filter(['status' => $value, 'q' => request('q'), 'discipline' => request('discipline')])) }}" @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-soft dark:bg-white/10 dark:text-white' => (string) $status === (string) $value,
                        'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => (string) $status !== (string) $value,
                    ])>
                        {{ $label }}
                        <span class="ml-1 text-xs text-slate-400 tabular-nums">{{ $value === '' ? $counts->sum() : ($counts[$value] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="flex items-center gap-2">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <select name="discipline" onchange="this.form.submit()" class="rounded-lg border-0 bg-slate-50 py-1.5 pr-8 pl-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                    <option value="">Toutes disciplines</option>
                    @foreach (Discipline::options() as $value => $label)<option value="{{ $value }}" @selected(request('discipline') === $value)>{{ $label }}</option>@endforeach
                </select>
                <div class="relative">
                    <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Compétition ou organisateur…" class="w-64 rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                </div>
            </form>
        </div>

        <div class="p-5">
            @if ($competitions->isEmpty())
                <x-ui.empty icon="trophy" title="Aucune compétition" description="Aucun résultat pour ces filtres." />
            @else
                <x-ui.table>
                    <x-slot:head><th>Compétition</th><th>Organisateur</th><th>Statut</th><th>Participants</th><th>Jury · matchs</th><th class="!text-right">Votes</th><th></th></x-slot:head>
                    @foreach ($competitions as $competition)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-5" /></span>
                                    <div>
                                        <a href="{{ route('admin.organizers.competitions.show', [$competition->organizer, $competition]) }}" class="font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-300">{{ $competition->name }}</a>
                                        <p class="text-xs text-slate-500">{{ $competition->discipline->label() }} · {{ $competition->mode->label() }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('admin.organizers.show', $competition->organizer) }}" class="hover:text-brand-600">{{ $competition->organizer->name }}</a>
                                @unless ($competition->organizer->isVerified())<div class="mt-1"><x-ui.badge :value="$competition->organizer->status" /></div>@endunless
                            </td>
                            <td><x-ui.badge :value="$competition->status" /></td>
                            <td class="tabular-nums">{{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }}</td>
                            <td class="text-slate-500 tabular-nums">{{ $competition->judges_count }} · {{ $competition->matches_count }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $fmt($competition->public_votes_count) }}</td>
                            <td class="text-right"><x-ui.button size="sm" variant="secondary" :href="route('admin.organizers.competitions.show', [$competition->organizer, $competition])" icon="eye">Détails</x-ui.button></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>

        @if ($competitions->hasPages())
            <x-slot:footer>{{ $competitions->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</x-layouts.app>
