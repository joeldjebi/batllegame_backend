@props(['phase', 'organizer', 'competition', 'canRun' => false])

@php
    use App\Enums\BracketSide;

    $sides = $phase->matches->groupBy(fn ($m) => $m->bracket?->value)->sortKeysUsing(
        fn ($a, $b) => array_search($a, ['gagnants', 'perdants', 'grande_finale']) <=> array_search($b, ['gagnants', 'perdants', 'grande_finale'])
    );

@endphp

<div class="space-y-8">
    @foreach ($sides as $side => $matches)
        @php($rounds = $matches->groupBy('round')->sortKeys())
        <div>
            @if ($sides->count() > 1)
                <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <span @class(['size-2 rounded-full', 'bg-brand-500' => $side === 'gagnants', 'bg-amber-500' => $side === 'perdants', 'bg-fuchsia-500' => $side === 'grande_finale'])></span>
                    {{ BracketSide::from($side)->label() }}
                </h4>
            @endif
            <div class="-mx-5 overflow-x-auto px-5 pb-2">
                <div class="flex min-w-max gap-10">
                    @foreach ($rounds as $round => $roundMatches)
                        <div class="bracket-round flex w-60 flex-col">
                            <p class="mb-3 text-center text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{{ \App\Services\Competition\RoundLabel::for(BracketSide::tryFrom((string) $side), $round, $rounds->keys()->max()) }}</p>
                            <div class="flex flex-1 flex-col justify-around gap-4">
                                @foreach ($roundMatches->sortBy('bracket_position') as $match)
                                    <x-bo.match-card :match="$match" :organizer="$organizer" :competition="$competition" :can-run="$canRun" :onsite="$phase->effectiveMode() === \App\Enums\CompetitionMode::OnSite" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
