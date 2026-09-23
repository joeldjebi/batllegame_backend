@props(['portal' => 'artist'])

{{-- Mobile bottom navigation: Accueil first, then the portal tabs. The active tab is the
     exact route when one matches, otherwise the tab of the current area (e.g. a competition
     page of the fan portal highlights « Voter »). --}}
@php
    $tabs = match ($portal) {
        'jury' => [['home', 'Accueil', 'home'], ['jury.dashboard', 'Compétitions', 'trophy'], ['jury.password.edit', 'Compte', 'user-circle']],
        default => [['home', 'Accueil', 'home'], ['fan.dashboard', 'Voter', 'hand-thumb-up'], ['artist.dashboard', 'Mon espace', 'microphone']],
    };
    $current = collect($tabs)->first(fn ($tab) => request()->routeIs($tab[0]))
        ?? collect($tabs)->first(fn ($tab) => $tab[0] !== 'home' && request()->routeIs(Str::before($tab[0], '.').'.*'));
@endphp
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl sm:hidden dark:border-white/10 dark:bg-slate-950/95" aria-label="Navigation">
    <div class="grid" style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr))">
        @foreach ($tabs as [$route, $label, $icon])
            @php($active = $current && $current[0] === $route)
            <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif @class([
                'relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-semibold transition',
                'text-brand-600 dark:text-brand-300' => $active,
                'text-slate-500 dark:text-slate-400' => ! $active,
            ])>
                @if ($active)<span class="absolute inset-x-6 top-0 h-0.5 rounded-full bg-brand-600 dark:bg-brand-400"></span>@endif
                <span @class(['grid h-8 w-14 place-items-center rounded-full transition', 'bg-brand-50 dark:bg-brand-500/15' => $active])><x-ui.icon :name="$icon" :variant="$active ? 's' : 'o'" class="size-5" /></span>
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
