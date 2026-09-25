@php
    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
    $member = auth('member')->user();
    // The hero shows a battle (1 vs 1); groups only appear in the live list.
    $featured = $live->first(fn ($match) => ! $match->isGroupMatch());
    $words = ['rap', 'chant', 'freestyle', 'slam', 'beatbox'];
    $band = collect(['Rap', 'Chant', 'Freestyle', 'Slam', 'Beatbox', 'En ligne', 'Sur scène', 'Jury', 'Vote du public'])
        ->merge($artistNames)->values();
    $featuredSlots = $featured?->slots->filter->participant->values() ?? collect();
    // Real vote share of the featured battle; the card is labelled as a preview without one.
    $votes = $featured ? $featured->publicVotes()->selectRaw('participant_id, count(*) as total')->groupBy('participant_id')->pluck('total', 'participant_id') : collect();
    $total = max(1, $votes->sum());
    $shares = $featuredSlots->map(fn ($slot) => $votes->sum() ? (int) round(($votes[$slot->participant_id] ?? 0) / $total * 100) : 50)->pad(2, 50)->all();
@endphp

<!DOCTYPE html>
<html lang="fr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Battle Game · Les battles de musique, en ligne et sur scène</title>
    <meta name="description" content="Suivez les battles de rap, chant et freestyle, votez pour vos artistes préférés ou inscrivez-vous pour concourir.">
    <script>
        (() => {
            document.documentElement.classList.add('js');
            const mode = localStorage.getItem('theme') ?? 'light';
            if (mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full overflow-x-hidden bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    x-data="{ scrolled: false }" x-on:scroll.window="scrolled = window.scrollY > 40">

    {{-- Navigation: transparent over the hero, solid once scrolled --}}
    <header class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
        :class="scrolled ? 'border-b border-slate-200/70 bg-white/90 backdrop-blur-xl dark:border-white/5 dark:bg-slate-950/90' : 'border-b border-transparent'">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="bolt" variant="s" class="size-5" /></span>
                <span class="font-display text-lg font-bold tracking-tight transition-colors" :class="scrolled ? 'text-slate-900 dark:text-white' : 'text-white'">Battle Game</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium md:flex" :class="scrolled ? 'text-slate-600 dark:text-slate-300' : 'text-white/75'">
                <a href="#direct" class="rounded-lg px-3 py-2 transition hover:text-brand-500">En direct</a>
                <a href="#competitions" class="rounded-lg px-3 py-2 transition hover:text-brand-500">Compétitions</a>
                <a href="#fonctionnement" class="rounded-lg px-3 py-2 transition hover:text-brand-500">Comment ça marche</a>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <x-ui.dropdown width="w-40">
                    <x-slot:trigger>
                        <button type="button" class="rounded-xl p-2 transition hover:bg-white/10" :class="scrolled ? 'text-slate-500 dark:text-slate-400' : 'text-white/70'" title="Thème">
                            <x-ui.icon name="sun" class="size-5 dark:hidden" /><x-ui.icon name="moon" class="hidden size-5 dark:block" />
                        </button>
                    </x-slot:trigger>
                    <x-ui.dropdown-item icon="sun" x-on:click="$store.theme.set('light')">Clair</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="moon" x-on:click="$store.theme.set('dark')">Sombre</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="computer-desktop" x-on:click="$store.theme.set('system')">Système</x-ui.dropdown-item>
                </x-ui.dropdown>

                @if ($member)
                    <x-ui.button size="sm" variant="secondary" :href="route('fan.dashboard')" icon="hand-thumb-up">Voter</x-ui.button>
                    <x-ui.button size="sm" :href="route('artist.dashboard')" icon="microphone">Mon espace</x-ui.button>
                @else
                    <x-ui.dropdown width="w-64">
                        <x-slot:trigger>
                            <x-ui.button size="sm" variant="secondary" icon-right="chevron-down">Se connecter</x-ui.button>
                        </x-slot:trigger>
                        <p class="px-2.5 pt-1.5 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Avec mon téléphone</p>
                        <x-ui.dropdown-item :href="route('fan.login')" icon="hand-thumb-up">Public — je vote</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('artist.login')" icon="microphone">Artiste — je participe</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('jury.login')" icon="scale">Jury — je note</x-ui.dropdown-item>
                        <div class="my-1 h-px bg-slate-100 dark:bg-white/10"></div>
                        <p class="px-2.5 pt-1.5 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Avec mon email</p>
                        <x-ui.dropdown-item :href="route('login')" icon="building-office-2">Organisateur — back-office</x-ui.dropdown-item>
                        @if (config('organizers.self_signup'))
                            <x-ui.dropdown-item :href="route('organizers.signup')" icon="rocket-launch">Créer un espace organisateur</x-ui.dropdown-item>
                        @endif
                    </x-ui.dropdown>
                    <x-ui.button size="sm" :href="route('fan.register')" class="hidden sm:inline-flex">Créer un compte</x-ui.button>
                @endif
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative isolate flex min-h-[min(100svh,60rem)] items-center overflow-hidden bg-slate-950 pt-16 text-white"
        x-data="pointerParallax" x-on:mousemove="move($event)" x-on:mouseleave="reset()">

        {{-- Background layers (solid shapes, no gradients) --}}
        <svg class="parallax absolute inset-0 -z-10 h-full w-full text-white/[0.06]" style="--speed: 0.25" aria-hidden="true">
            <defs>
                <pattern id="hero-grid" width="64" height="64" patternUnits="userSpaceOnUse">
                    <path d="M64 0H0V64" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#hero-grid)" />
        </svg>
        <div class="parallax pointer-events-none absolute inset-0 -z-10" style="--speed: 0.1"><div class="depth absolute -top-40 -right-40 -z-10 size-[42rem] rounded-full border border-white/[0.07]" style="--depth: 18"></div></div>
        <div class="parallax pointer-events-none absolute inset-0 -z-10" style="--speed: 0.18"><div class="depth absolute -top-16 -right-16 -z-10 size-[30rem] rounded-full border border-brand-500/30" style="--depth: 28"></div></div>
        <div class="depth absolute bottom-24 -left-24 -z-10 size-72 rounded-full bg-brand-600/15" style="--depth: 36"></div>
        <div class="depth absolute top-1/3 left-1/2 -z-10 size-3 rounded-full bg-fuchsia-400" style="--depth: 60"></div>
        <div class="depth absolute top-24 left-1/4 -z-10 size-2 rounded-full bg-brand-300" style="--depth: 80"></div>
        <div class="depth absolute right-1/3 bottom-40 -z-10 size-2.5 rounded-full bg-emerald-400" style="--depth: 50"></div>

        {{-- Equalizer along the bottom --}}
        <div class="equalizer absolute inset-x-0 bottom-0 -z-10 flex h-40 items-end justify-between gap-1 px-2 opacity-30" aria-hidden="true">
            @foreach (range(1, 64) as $i)
                <span class="w-full rounded-t-sm bg-brand-600" style="height: {{ 20 + ($i * 37) % 80 }}%; --delay: -{{ ($i * 0.13) % 1.1 }}s"></span>
            @endforeach
        </div>

        <div class="mx-auto grid w-full max-w-7xl items-center gap-16 px-4 py-20 sm:px-6 lg:grid-cols-[1.1fr_1fr]">
            <div>
                <a href="#direct" x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)" class="reveal inline-flex items-center gap-2.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/15 backdrop-blur transition hover:bg-white/15">
                    <span class="relative flex size-2"><span class="ping-slow absolute inline-flex size-full rounded-full bg-fuchsia-400"></span><span class="relative inline-flex size-2 rounded-full bg-fuchsia-400"></span></span>
                    {{ $stats['live'] ? $stats['live'].' battle(s) en direct' : 'Les battles de musique, en ligne et sur scène' }}
                    <x-ui.icon name="arrow-right" variant="m" class="size-3.5" />
                </a>

                <h1 class="mt-8 font-display text-5xl leading-[1.05] font-extrabold tracking-tight sm:text-6xl xl:text-7xl">
                    <span class="reveal block" x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)">La scène où le</span>
                    <span class="reveal relative block h-[1.1em] overflow-hidden text-brand-400" style="--delay: 120ms" x-data="rotator(@js($words))" x-init="setTimeout(() => $el.classList.add('is-visible'), 60)">
                        <template x-for="(word, i) in words" :key="word">
                            <span class="absolute inset-x-0 top-0 transition duration-700 ease-[cubic-bezier(0.16,1,0.3,1)]"
                                :class="i === index ? 'translate-y-0 opacity-100' : (i < index ? '-translate-y-full opacity-0' : 'translate-y-full opacity-0')"
                                x-text="word"></span>
                        </template>
                        <span class="invisible">freestyle</span>
                    </span>
                    <span class="reveal block" style="--delay: 240ms" x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)">se joue en direct.</span>
                </h1>

                <p x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)" class="reveal mt-7 max-w-xl text-lg leading-relaxed text-white/70" style="--delay: 360ms">
                    Des poules à la grande finale, suivez chaque battle, regardez les prestations et faites gagner vos artistes.
                    Artistes : inscrivez-vous et envoyez vos performances à chaque étape.
                </p>

                <div x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)" class="reveal mt-10 flex flex-wrap gap-3" style="--delay: 480ms">
                    <x-ui.button size="lg" :href="$member ? route('fan.dashboard') : route('fan.register')" icon="hand-thumb-up" class="!px-6 !py-3 !text-base">Je veux voter</x-ui.button>
                    <x-ui.button size="lg" :href="$member ? route('artist.dashboard') : route('artist.register')" icon="microphone" class="!bg-white !px-6 !py-3 !text-base !text-slate-900 hover:!bg-slate-100">Je suis artiste</x-ui.button>
                </div>

                <div x-data x-init="setTimeout(() => $el.classList.add('is-visible'), 60)" class="reveal mt-12 flex items-center gap-4" style="--delay: 600ms">
                    <div class="flex -space-x-2.5">
                        @foreach ($artistNames->take(5) as $name)
                            <x-ui.avatar :name="$name" size="sm" class="!ring-slate-950" />
                        @endforeach
                    </div>
                    <p class="text-sm text-white/60"><span class="font-semibold text-white">{{ $fmt($stats['artists']) }} artistes</span> en compétition · <span class="font-semibold text-white">{{ $fmt($stats['votes']) }}</span> votes</p>
                </div>
            </div>

            {{-- Floating product cards --}}
            <div class="relative hidden h-[34rem] lg:block" aria-hidden="true">
                <div class="depth float absolute top-6 left-4 w-80 rounded-3xl bg-white p-6 text-slate-900 shadow-2xl shadow-black/40 dark:bg-slate-900 dark:text-white" style="--depth: 22">
                    <div class="flex items-center justify-between text-xs">
                        @if ($featured)
                            <span class="inline-flex items-center gap-2 font-semibold text-fuchsia-600 dark:text-fuchsia-300"><span class="size-2 animate-pulse rounded-full bg-fuchsia-500"></span> Vote en direct</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-500 dark:bg-white/10">Aperçu</span>
                        @endif
                        <span class="text-slate-400">{{ $featured?->stage?->name ?? 'Demi-finale' }}</span>
                    </div>
                    <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                        @foreach ([0, 1] as $i)
                            @if ($i === 1)<span class="font-display text-xl font-extrabold text-slate-300">VS</span>@endif
                            @php $artist = $featuredSlots->get($i)?->participant->stage_name ?? ($i ? 'Artiste B' : 'Artiste A'); @endphp
                            <div><x-ui.avatar :name="$artist" size="lg" class="mx-auto" /><p class="mt-2 truncate text-sm font-semibold">{{ $artist }}</p></div>
                        @endforeach
                    </div>
                    <div class="mt-6 space-y-2" x-data x-intersect.once="$el.classList.add('is-visible')">
                        <div class="flex justify-between text-xs"><span class="font-semibold">{{ $shares[0] }} %</span><span class="text-slate-400">{{ $shares[1] }} %</span></div>
                        <div class="flex h-2 gap-1 overflow-hidden rounded-full">
                            <span class="fill-bar h-full rounded-full bg-brand-600" style="width: {{ max(4, $shares[0]) }}%; --delay: 400ms"></span>
                            <span class="fill-bar h-full rounded-full bg-slate-200 dark:bg-white/10" style="width: {{ max(4, $shares[1]) }}%; --delay: 600ms"></span>
                        </div>
                    </div>
                </div>

                <div class="depth float absolute top-72 right-0 w-64 rounded-2xl bg-white p-5 text-slate-900 shadow-2xl shadow-black/40 dark:bg-slate-900 dark:text-white" style="--depth: 44; --delay: -2s">
                    <p class="flex items-center gap-2 text-xs font-semibold text-slate-500"><x-ui.icon name="scale" class="size-4 text-brand-600" /> Note du jury</p>
                    <div class="mt-4 space-y-3" x-data x-intersect.once="$el.classList.add('is-visible')">
                        @foreach ([['Flow', 0.85, '8,5'], ['Lyrics', 0.9, '9'], ['Scène', 0.7, '7']] as $index => [$label, $fill, $value])
                            <div>
                                <div class="flex justify-between text-xs"><span>{{ $label }}</span><span class="font-display font-bold">{{ $value }}/10</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"><div class="fill-bar h-full rounded-full bg-emerald-500" style="--fill: {{ $fill }}; --delay: {{ 500 + $index * 200 }}ms"></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="depth float absolute bottom-24 left-6 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-slate-900 shadow-2xl shadow-black/40 dark:bg-slate-900 dark:text-white" style="--depth: 64; --delay: -4s">
                    <span class="grid size-9 place-items-center rounded-full bg-emerald-500 text-white"><x-ui.icon name="check" variant="m" class="size-5" /></span>
                    <div><p class="text-sm font-semibold">Vote enregistré</p><p class="text-xs text-slate-500">Merci, c'est vous le jury populaire</p></div>
                </div>

                <div class="depth float absolute right-6 bottom-0 flex items-center gap-3 rounded-2xl bg-brand-600 px-4 py-3 text-white shadow-2xl shadow-brand-900/50" style="--depth: 30; --delay: -3s">
                    <x-ui.icon name="trophy" variant="s" class="size-6 text-amber-300" />
                    <div><p class="text-sm font-semibold">Qualifié pour la finale</p><p class="text-xs text-white/70">1er de la poule A</p></div>
                </div>
            </div>
        </div>

        <a href="#bande" class="absolute bottom-8 left-1/2 hidden -translate-x-1/2 flex-col items-center gap-2 text-xs text-white/50 transition hover:text-white sm:flex">
            Découvrir
            <span class="grid h-9 w-6 justify-center rounded-full border border-white/30 pt-1.5"><span class="h-2 w-1 animate-bounce rounded-full bg-white/70"></span></span>
        </a>
    </section>

    {{-- Scrolling band --}}
    <section id="bande" class="overflow-hidden border-y border-brand-700 bg-brand-600 py-5 text-white">
        <div class="marquee gap-10 font-display text-2xl font-bold tracking-tight whitespace-nowrap uppercase" style="--duration: 45s">
            @foreach ([1, 2] as $copy)
                <div class="flex items-center gap-10" @if ($copy === 2) aria-hidden="true" @endif>
                    @foreach ($band as $item)
                        <span>{{ $item }}</span><x-ui.icon name="bolt" variant="s" class="size-5 text-white/40" />
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <main>
        {{-- Stats --}}
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Compétitions', $stats['competitions'], 'trophy'],
                    ['Artistes', $stats['artists'], 'microphone'],
                    ['Votes du public', $stats['votes'], 'hand-thumb-up'],
                    ['Organisateurs', $stats['organizers'], 'building-office-2'],
                ] as $index => [$label, $value, $icon])
                    <div class="reveal rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-900/5 dark:bg-white/[0.03] dark:ring-white/10" style="--delay: {{ $index * 100 }}ms"
                        x-data="counter({{ (int) $value }})" x-intersect.once="$el.classList.add('is-visible'); start()">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon :name="$icon" class="size-5" /></span>
                        <p class="mt-6 font-display text-5xl font-extrabold tracking-tight tabular-nums" x-text="formatted">{{ $fmt($value) }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Live votes --}}
        <section id="direct" data-live="landing-live" class="scroll-mt-20 bg-slate-50 py-24 dark:bg-white/[0.02]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="reveal flex flex-wrap items-end justify-between gap-4" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <div>
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-fuchsia-600 dark:text-fuchsia-300"><span class="size-2 animate-pulse rounded-full bg-fuchsia-500"></span> En direct</p>
                        <h2 class="mt-2 font-display text-4xl font-extrabold tracking-tight">Votes en cours</h2>
                        <p class="mt-2 max-w-xl text-slate-500">Regardez les prestations et départagez les artistes : chaque voix compte dans le score final.</p>
                    </div>
                    <x-ui.button variant="secondary" :href="route('fan.dashboard')" icon-right="arrow-right">Toutes les compétitions</x-ui.button>
                </div>

                @if ($live->isEmpty())
                    <x-ui.empty icon="clock" title="Aucun vote en cours" description="Les votes s'ouvrent pendant les compétitions : revenez bientôt." class="mt-10" />
                @else
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        @foreach ($live as $index => $match)
                            <a href="{{ route('fan.competitions.show', $match->competition) }}" x-data x-intersect.once="$el.classList.add('is-visible')" style="--delay: {{ $index * 120 }}ms"
                                class="reveal group relative overflow-hidden rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-900/5 transition duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:ring-fuchsia-300 dark:bg-slate-900 dark:ring-white/10">
                                <div class="flex items-center justify-between text-xs">
                                    <x-ui.badge tone="fuchsia">Vote ouvert</x-ui.badge>
                                    <span class="text-slate-500">{{ $fmt($match->public_votes_count) }} vote(s)</span>
                                </div>
                                <div class="mt-6 flex items-center justify-center gap-4">
                                    @if ($match->isGroupMatch())
                                        @php
                                            $artists = $match->slots->filter(fn ($slot) => $slot->participant && ! $slot->is_forfeit)->values();
                                        @endphp
                                        <div class="text-center">
                                            <div class="flex justify-center -space-x-3">
                                                @foreach ($artists->take(4) as $slot)<x-ui.avatar :name="$slot->participant->stage_name" class="ring-2 ring-white dark:ring-slate-900" />@endforeach
                                                @if ($artists->count() > 4)<span class="grid size-10 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 ring-2 ring-white dark:bg-white/10 dark:text-slate-300 dark:ring-slate-900">+{{ $artists->count() - 4 }}</span>@endif
                                            </div>
                                            <p class="mt-3 font-display text-lg font-bold">{{ $match->group?->name }}</p>
                                            <p class="text-sm text-slate-500">{{ $artists->count() }} artistes · 1 vote par phase</p>
                                        </div>
                                    @else
                                        @foreach ($match->slots->filter->participant->values() as $i => $slot)
                                            @if ($i === 1)<span class="font-display text-sm font-extrabold text-slate-300">VS</span>@endif
                                            <div class="text-center transition duration-300 group-hover:scale-105">
                                                <x-ui.avatar :name="$slot->participant->stage_name" size="lg" class="mx-auto" />
                                                <p class="mt-2 max-w-24 truncate text-sm font-semibold">{{ $slot->participant->stage_name }}</p>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <p class="mt-6 text-sm text-slate-500">{{ $match->competition->name }} · {{ $match->stage?->name }}</p>
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 dark:text-brand-300">Voter maintenant <x-ui.icon name="arrow-right" variant="m" class="size-4 transition group-hover:translate-x-1" /></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Competitions --}}
        <section id="competitions" data-live="landing-competitions" class="scroll-mt-20 py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="reveal" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight">Compétitions en cours</h2>
                    <p class="mt-2 text-slate-500">Suivez les résultats, étape par étape.</p>
                </div>
                @if ($inProgress->isEmpty())
                    <x-ui.empty icon="trophy" title="Aucune compétition en cours" class="mt-10" />
                @else
                    <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($inProgress as $index => $competition)
                            <a href="{{ route('fan.competitions.show', $competition) }}" x-data x-intersect.once="$el.classList.add('is-visible')" style="--delay: {{ $index * 100 }}ms"
                                class="reveal group overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-slate-900/5 transition duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:ring-brand-300 dark:bg-slate-900 dark:ring-white/10">
                                <div class="relative h-36 overflow-hidden bg-brand-600">
                                    <x-ui.icon :name="$competition->discipline->icon()" class="absolute -right-4 -bottom-6 size-36 text-white/15 transition duration-500 group-hover:scale-110 group-hover:-rotate-6" />
                                    <div class="equalizer absolute bottom-0 left-5 flex h-12 items-end gap-1 opacity-60">
                                        @foreach (range(1, 12) as $i)<span class="w-1.5 rounded-t-sm bg-white" style="height: {{ 30 + ($i * 29) % 70 }}%; --delay: -{{ ($i * 0.17) % 1.1 }}s"></span>@endforeach
                                    </div>
                                    @if ($competition->voting_matches_count)
                                        <x-ui.badge tone="fuchsia" class="absolute top-4 left-4 !bg-white">{{ $competition->voting_matches_count }} vote(s) en cours</x-ui.badge>
                                    @endif
                                </div>
                                <div class="p-6">
                                    <p class="font-display text-lg font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $competition->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }} · {{ $competition->mode->label() }}{{ $competition->locationLabel() ? ' · '.$competition->locationLabel() : '' }}</p>
                                    <p class="mt-4 flex items-center gap-1.5 text-xs text-slate-400"><x-ui.icon name="users" variant="m" class="size-4" /> {{ $competition->participants_count }} artiste(s)</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Bracket showcase with parallax --}}
        <section class="relative overflow-hidden bg-slate-950 py-28 text-white">
            <div class="parallax absolute -top-32 -left-32 size-96 rounded-full border border-white/[0.07]" style="--speed: -0.08"></div>
            <div class="parallax absolute -right-20 -bottom-40 size-[28rem] rounded-full bg-brand-600/15" style="--speed: -0.12"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2">
                <div class="reveal" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <p class="text-sm font-semibold text-brand-300">Du premier tour à la finale</p>
                    <h2 class="mt-3 font-display text-4xl font-extrabold tracking-tight sm:text-5xl">Chaque étape compte.</h2>
                    <p class="mt-5 text-lg text-white/65">Poules, élimination directe ou double élimination : le tableau se met à jour à chaque verdict, les qualifiés avancent automatiquement.</p>
                    <ul class="mt-8 space-y-4">
                        @foreach ([
                            ['scale', 'Jury + public', 'Des scores pondérés, transparents et recalculables.'],
                            ['cloud-arrow-up', 'En ligne ou sur scène', 'Vidéos soumises à chaque étape, ou vote en direct avec code de salle.'],
                            ['shield-check', 'Votes fiables', 'Un vote par personne et par battle, numéro vérifié par SMS.'],
                        ] as $index => [$icon, $title, $text])
                            <li class="reveal flex gap-4" style="--delay: {{ 200 + $index * 150 }}ms" x-data x-intersect.once="$el.classList.add('is-visible')">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/10 ring-1 ring-white/15"><x-ui.icon :name="$icon" class="size-5 text-brand-300" /></span>
                                <div><p class="font-semibold">{{ $title }}</p><p class="text-sm text-white/60">{{ $text }}</p></div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Animated bracket drawing --}}
                <div class="reveal relative" x-data x-intersect.once="$el.classList.add('is-visible'); $refs.lines.classList.add('is-visible')" style="--delay: 150ms">
                    <svg x-ref="lines" class="draw absolute inset-0 h-full w-full" viewBox="0 0 520 360" fill="none" aria-hidden="true">
                        <path d="M170 60 H210 V120 H250" stroke="rgb(167 139 250 / 0.6)" stroke-width="2" style="--delay: 200ms" />
                        <path d="M170 180 H210 V120" stroke="rgb(167 139 250 / 0.6)" stroke-width="2" style="--delay: 350ms" />
                        <path d="M170 240 H210 V300 H250" stroke="rgb(167 139 250 / 0.6)" stroke-width="2" style="--delay: 500ms" />
                        <path d="M170 360 H210 V300" stroke="rgb(167 139 250 / 0.6)" stroke-width="2" style="--delay: 650ms" />
                        <path d="M410 120 H440 V210 H470" stroke="rgb(232 121 249 / 0.8)" stroke-width="2" style="--delay: 900ms" />
                        <path d="M410 300 H440 V210" stroke="rgb(232 121 249 / 0.8)" stroke-width="2" style="--delay: 1050ms" />
                    </svg>
                    <div class="relative grid grid-cols-[1fr_1fr] gap-x-16 gap-y-4 text-sm">
                        <div class="space-y-4">
                            @foreach ([['MC Flow', true], ['Lady Rime', false], ['Big Verse', true], ['Kid Punch', false]] as $index => [$name, $won])
                                <div class="reveal flex items-center justify-between rounded-xl px-4 py-3 ring-1 {{ $won ? 'bg-white text-slate-900 ring-white' : 'bg-white/5 text-white/50 ring-white/10' }}" style="--delay: {{ $index * 120 }}ms" x-data x-intersect.once="$el.classList.add('is-visible')">
                                    <span class="font-semibold">{{ $name }}</span>@if ($won)<x-ui.icon name="check" variant="m" class="size-4 text-emerald-500" />@endif
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-col justify-around">
                            <div class="reveal rounded-xl bg-white px-4 py-3 text-slate-900 ring-1 ring-white" style="--delay: 700ms" x-data x-intersect.once="$el.classList.add('is-visible')"><span class="font-semibold">MC Flow</span></div>
                            <div class="reveal flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-3 font-semibold ring-1 ring-brand-400" style="--delay: 1100ms" x-data x-intersect.once="$el.classList.add('is-visible')">
                                <x-ui.icon name="trophy" variant="s" class="size-5 text-amber-300" /> Finale
                            </div>
                            <div class="reveal rounded-xl bg-white px-4 py-3 text-slate-900 ring-1 ring-white" style="--delay: 850ms" x-data x-intersect.once="$el.classList.add('is-visible')"><span class="font-semibold">Big Verse</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Open registrations --}}
        <section class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="reveal flex flex-wrap items-end justify-between gap-4" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <div>
                        <h2 class="font-display text-4xl font-extrabold tracking-tight">Inscriptions ouvertes</h2>
                        <p class="mt-2 text-slate-500">Artistes : c'est le moment de tenter votre chance.</p>
                    </div>
                    <x-ui.button :href="$member ? route('artist.dashboard') : route('artist.register')" icon="user-plus">Devenir artiste</x-ui.button>
                </div>
                @if ($open->isEmpty())
                    <x-ui.empty icon="calendar" title="Aucune inscription ouverte pour le moment" class="mt-10" />
                @else
                    <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($open as $index => $competition)
                            <div x-data x-intersect.once="$el.classList.add('is-visible')" style="--delay: {{ $index * 100 }}ms"
                                class="reveal flex flex-col rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-900/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl dark:bg-slate-900 dark:ring-white/10">
                                <div class="flex items-start justify-between">
                                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-6" /></span>
                                    <x-ui.badge tone="gray" :dot="false" :icon="$competition->mode->icon()">{{ $competition->mode->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-5 font-display text-lg font-semibold">{{ $competition->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }}</p>
                                @php $fill = $competition->max_participants ? min(1, $competition->participants_count / $competition->max_participants) : 0.3; @endphp
                                <div class="mt-5">
                                    <div class="flex justify-between text-xs text-slate-500">
                                        <span>{{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }} inscrits</span>
                                        <span>{{ $competition->entry_fee ? $fmt($competition->entry_fee).' '.$competition->currency : 'Gratuit' }}</span>
                                    </div>
                                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"><div class="fill-bar h-full rounded-full bg-brand-600" style="--fill: {{ $fill }}; --delay: {{ 300 + $index * 100 }}ms"></div></div>
                                    @if ($competition->registration_ends_at)<p class="mt-2 text-xs text-slate-400">Jusqu'au {{ $competition->registration_ends_at->translatedFormat('d F') }}</p>@endif
                                </div>
                                <x-ui.button class="mt-6 w-full" variant="soft" :href="$member ? route('artist.dashboard') : route('artist.register')" icon="user-plus">S'inscrire</x-ui.button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- How it works --}}
        <section id="fonctionnement" class="scroll-mt-20 bg-slate-50 py-24 dark:bg-white/[0.02]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="reveal text-center" x-data x-intersect.once="$el.classList.add('is-visible')">
                    <h2 class="font-display text-4xl font-extrabold tracking-tight">Comment ça marche</h2>
                    <p class="mt-2 text-slate-500">Trois rôles, une même scène.</p>
                </div>
                <div class="relative mt-14 grid gap-8 md:grid-cols-3">
                    <div class="absolute top-7 right-[16%] left-[16%] hidden h-px bg-slate-200 md:block dark:bg-white/10">
                        <div class="fill-bar h-full bg-brand-600" x-data x-intersect.once="$el.classList.add('is-visible')" style="--delay: 300ms"></div>
                    </div>
                    @foreach ([
                        ['hand-thumb-up', 'Public', 'Créez votre compte avec votre numéro, vérifiez-le par SMS et votez une fois par battle. En salle, entrez le code affiché à l\'écran.', route('fan.register'), 'Créer mon compte'],
                        ['microphone', 'Artistes', 'Inscrivez-vous aux compétitions ouvertes. En ligne, envoyez votre vidéo ou votre son à chaque étape avant la date limite.', route('artist.register'), 'Devenir artiste'],
                        ['scale', 'Jury', 'Désignés par les organisateurs, les jurés notent chaque prestation critère par critère depuis leur espace.', route('jury.login'), 'Espace jury'],
                    ] as $index => [$icon, $title, $text, $url, $cta])
                        <div class="reveal relative text-center" style="--delay: {{ $index * 150 }}ms" x-data x-intersect.once="$el.classList.add('is-visible')">
                            <span class="relative mx-auto grid size-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-lift ring-8 ring-slate-50 dark:ring-slate-950"><x-ui.icon :name="$icon" class="size-7" /></span>
                            <p class="mt-2 text-xs font-bold tracking-widest text-brand-600 dark:text-brand-300">0{{ $index + 1 }}</p>
                            <h3 class="mt-3 font-display text-xl font-bold">{{ $title }}</h3>
                            <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $text }}</p>
                            <a href="{{ $url }}" class="group mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">{{ $cta }} <x-ui.icon name="arrow-right" variant="m" class="size-4 transition group-hover:translate-x-1" /></a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Final call to action --}}
        <section class="relative overflow-hidden bg-brand-600 py-24 text-white">
            <div class="parallax absolute -top-24 -right-24 size-80 rounded-full border-[40px] border-white/10" style="--speed: -0.1"></div>
            <div class="parallax absolute -bottom-32 -left-16 size-72 rounded-full bg-brand-700" style="--speed: -0.06"></div>
            <div class="equalizer absolute right-12 bottom-0 hidden h-24 items-end gap-1.5 opacity-40 md:flex" aria-hidden="true">
                @foreach (range(1, 18) as $i)<span class="w-2 rounded-t-sm bg-white" style="height: {{ 25 + ($i * 41) % 75 }}%; --delay: -{{ ($i * 0.11) % 1.1 }}s"></span>@endforeach
            </div>
            <div class="reveal relative mx-auto max-w-3xl px-4 text-center" x-data x-intersect.once="$el.classList.add('is-visible')">
                <h2 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">La prochaine battle commence bientôt.</h2>
                <p class="mt-5 text-lg text-white/80">Créez votre compte en moins d'une minute : votez, participez ou suivez vos artistes.</p>
                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <x-ui.button size="lg" :href="$member ? route('fan.dashboard') : route('fan.register')" icon="hand-thumb-up" class="!bg-white !px-6 !py-3 !text-base !text-brand-700 hover:!bg-slate-100">Je veux voter</x-ui.button>
                    <x-ui.button size="lg" :href="$member ? route('artist.dashboard') : route('artist.register')" icon="microphone" class="!bg-slate-950 !px-6 !py-3 !text-base hover:!bg-slate-900">Je suis artiste</x-ui.button>
                </div>
            </div>
        </section>

        {{-- Organizers --}}
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="reveal flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-slate-900 px-8 py-10 text-white sm:px-12 dark:bg-white/5" x-data x-intersect.once="$el.classList.add('is-visible')">
                <div class="max-w-xl">
                    <h2 class="font-display text-2xl font-bold">Vous organisez des battles ?</h2>
                    <p class="mt-2 text-white/70">Poules, brackets, jury, vote du public et soumissions en ligne : {{ $fmt($stats['organizers']) }} organisateur(s) pilotent déjà leurs compétitions sur Battle Game.{{ config('organizers.self_signup') ? ' Créez votre espace gratuitement, en quelques minutes.' : ' Les espaces organisateurs sont ouverts par notre équipe.' }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if (config('organizers.self_signup'))
                        <x-ui.button size="lg" :href="route('organizers.signup')" class="!bg-white !text-slate-900 hover:!bg-slate-100" icon="rocket-launch">Créer mon espace</x-ui.button>
                    @endif
                    <x-ui.button size="lg" :href="route('login')" variant="secondary" class="!bg-transparent !text-white !ring-white/30 hover:!bg-white/10" icon="building-office-2">Se connecter</x-ui.button>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-100 dark:border-white/5">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-slate-500 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-display font-bold text-slate-900 dark:text-white">
                <span class="grid size-7 place-items-center rounded-lg bg-brand-600 text-white"><x-ui.icon name="bolt" variant="s" class="size-4" /></span> Battle Game
            </a>
            <nav class="flex flex-wrap gap-5">
                <a href="{{ route('fan.dashboard') }}" class="hover:text-slate-900 dark:hover:text-white">Compétitions</a>
                <a href="{{ route('fan.login') }}" class="hover:text-slate-900 dark:hover:text-white">Public</a>
                <a href="{{ route('artist.login') }}" class="hover:text-slate-900 dark:hover:text-white">Artistes</a>
                <a href="{{ route('jury.login') }}" class="hover:text-slate-900 dark:hover:text-white">Jury</a>
                <a href="{{ route('login') }}" class="hover:text-slate-900 dark:hover:text-white">Organisateurs</a>
            </nav>
            <span>© {{ date('Y') }} Battle Game</span>
        </div>
    </footer>

    <x-realtime :channels="[\App\Realtime\Channel::LIVE]" />

    {{-- Signed-in members and judges keep their mobile tab bar, « Accueil » selected. --}}
    @if ($member || auth('jury')->check())
        <div class="h-20 sm:hidden"></div>
        <x-portal.bottom-nav :portal="$member ? 'artist' : 'jury'" />
    @endif
</body>
</html>
