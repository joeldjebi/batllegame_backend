<x-layouts.portal title="Mes compétitions">
    <x-ui.page-header title="Mes compétitions" description="Les compétitions pour lesquelles vous êtes membre du jury." />

    @if ($assignments->isEmpty())
        <x-ui.empty icon="scale" title="Aucune compétition" description="Aucun organisateur ne vous a encore confié de compétition." />
    @else
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($assignments as $assignment)
                @php($competition = $assignment->competition)
                <a href="{{ route('jury.competitions.show', $competition) }}" class="group flex flex-col rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-slate-900/60 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-5" /></span>
                        <x-ui.badge :value="$competition->status" />
                    </div>
                    <h2 class="mt-4 font-display text-base font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $competition->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->mode->label() }}</p>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm dark:border-white/5">
                        <span class="text-slate-500">{{ $competition->criteria_count }} critère(s)</span>
                        @if ($competition->voting_matches_count)
                            <x-ui.badge tone="fuchsia">{{ $competition->voting_matches_count }} match(s) à noter</x-ui.badge>
                        @else
                            <span class="text-slate-400">Rien à noter</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::user(auth('jury')->id()), ...$assignments->map(fn ($a) => \App\Realtime\Channel::jury($a->competition_id))->all()]" />
</x-layouts.portal>
