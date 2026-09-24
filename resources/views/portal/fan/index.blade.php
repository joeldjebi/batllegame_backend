<x-layouts.portal title="Compétitions">
    <x-ui.page-header title="Compétitions" description="Regardez les battles et votez pour vos artistes préférés." />

    @if ($competitions->isEmpty())
        <x-ui.empty icon="trophy" title="Aucune compétition pour le moment" />
    @else
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($competitions as $competition)
                <a href="{{ route('fan.competitions.show', $competition) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-slate-900/60 dark:ring-white/10">
                    <div class="relative h-20 bg-brand-600">
                        <x-ui.icon :name="$competition->discipline->icon()" class="absolute -right-2 -bottom-3 size-20 text-white/15" />
                        <div class="absolute top-3 left-3 flex gap-2">
                            <x-ui.badge :value="$competition->status" class="!bg-white/90 dark:!bg-slate-900/80" />
                            @if ($competition->voting_matches_count)<x-ui.badge tone="fuchsia" class="!bg-white/90">Vote en cours</x-ui.badge>@endif
                        </div>
                    </div>
                    <div class="p-5">
                        <h2 class="font-display font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $competition->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }} · {{ $competition->mode->label() }}{{ $competition->locationLabel() ? ' · '.$competition->locationLabel() : '' }}</p>
                        <p class="mt-3 text-xs text-slate-400">{{ $competition->participants_count }} artiste(s)</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::LIVE, ...$competitions->map(fn ($c) => \App\Realtime\Channel::competition($c->id))->all(), auth('member')->id() ? \App\Realtime\Channel::user(auth('member')->id()) : null]" />
</x-layouts.portal>
