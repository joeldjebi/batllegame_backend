@php
    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
    $member = auth('member')->user();
    $featured = $live->first();
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
            const mode = localStorage.getItem('theme') ?? 'system';
            if (mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" x-data="{ menu: false }">
    {{-- Navigation --}}
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl dark:border-white/5 dark:bg-slate-950/85">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="bolt" variant="s" class="size-5" /></span>
                <span class="font-display text-lg font-bold tracking-tight">Battle Game</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium text-slate-600 md:flex dark:text-slate-300">
                <a href="#direct" class="rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white">En direct</a>
                <a href="#competitions" class="rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white">Compétitions</a>
                <a href="#fonctionnement" class="rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white">Comment ça marche</a>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <x-ui.dropdown width="w-40">
                    <x-slot:trigger>
                        <button type="button" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/10" title="Thème">
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
                    </x-ui.dropdown>
                    <x-ui.button size="sm" :href="route('fan.register')" class="hidden sm:inline-flex">Créer un compte</x-ui.button>
                @endif
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="border-b border-slate-100 bg-slate-50 dark:border-white/5 dark:bg-slate-900/40">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 dark:bg-brand-500/10 dark:text-brand-200 dark:ring-brand-500/30">
                    <x-ui.icon name="bolt" variant="m" class="size-4" /> Rap · Chant · Freestyle · Slam
                </span>
                <h1 class="mt-6 font-display text-4xl leading-tight font-bold tracking-tight sm:text-5xl">
                    Les battles se jouent ici.<br><span class="text-brand-600 dark:text-brand-400">C'est vous qui décidez.</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-slate-600 dark:text-slate-300">
                    Suivez les compétitions en ligne et sur scène, regardez les prestations et votez pour vos artistes.
                    Vous êtes artiste ? Inscrivez-vous et envoyez vos performances à chaque étape.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button size="lg" :href="$member ? route('fan.dashboard') : route('fan.register')" icon="hand-thumb-up">Je veux voter</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" :href="$member ? route('artist.dashboard') : route('artist.register')" icon="microphone">Je suis artiste</x-ui.button>
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6">
                    @foreach ([['Compétitions', $stats['competitions']], ['Artistes', $stats['artists']], ['Votes', $stats['votes']]] as [$label, $value])
                        <div>
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="font-display text-3xl font-bold tabular-nums">{{ $fmt($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Featured live battle --}}
            <div class="rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10">
                @if ($featured)
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-fuchsia-600 dark:text-fuchsia-300"><span class="size-2 animate-pulse rounded-full bg-fuchsia-500"></span> En direct</span>
                        <span class="text-xs text-slate-500">{{ $featured->stage?->name }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ $featured->competition->name }}</p>
                    <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4">
                        @foreach ($featured->slots->filter->participant->values() as $index => $slot)
                            @if ($index === 1)<span class="font-display text-2xl font-bold text-slate-300 dark:text-slate-600">VS</span>@endif
                            <div class="text-center">
                                <x-ui.avatar :name="$slot->participant->stage_name" size="xl" class="mx-auto" />
                                <p class="mt-3 font-display font-semibold">{{ $slot->participant->stage_name }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-sm dark:border-white/5">
                        <span class="text-slate-500">{{ $fmt($featured->public_votes_count) }} vote(s)@if ($featured->voting_closes_at) · fin {{ $featured->voting_closes_at->diffForHumans() }}@endif</span>
                        <x-ui.button size="sm" :href="route('fan.competitions.show', $featured->competition)" icon-right="arrow-right">Voter</x-ui.button>
                    </div>
                @else
                    <div class="py-10 text-center">
                        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon name="microphone" class="size-7" /></span>
                        <p class="mt-4 font-display text-lg font-semibold">La prochaine battle arrive</p>
                        <p class="mt-1 text-sm text-slate-500">Créez votre compte pour être prêt à voter.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-6xl space-y-20 px-4 py-16 sm:px-6">
        {{-- Live votes --}}
        <section id="direct" class="scroll-mt-24">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-2xl font-bold">Votes en cours</h2>
                    <p class="mt-1 text-slate-500">Regardez les prestations et départagez les artistes.</p>
                </div>
                <x-ui.button variant="ghost" :href="route('fan.dashboard')" icon-right="arrow-right">Toutes les compétitions</x-ui.button>
            </div>
            @if ($live->isEmpty())
                <x-ui.empty icon="clock" title="Aucun vote en cours" description="Les votes s'ouvrent pendant les compétitions : revenez bientôt." class="mt-6" />
            @else
                <div class="mt-6 grid gap-5 md:grid-cols-3">
                    @foreach ($live as $match)
                        <a href="{{ route('fan.competitions.show', $match->competition) }}" class="group rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:ring-fuchsia-300 dark:bg-slate-900/60 dark:ring-white/10">
                            <div class="flex items-center justify-between text-xs">
                                <x-ui.badge tone="fuchsia">Vote ouvert</x-ui.badge>
                                <span class="text-slate-500">{{ $fmt($match->public_votes_count) }} vote(s)</span>
                            </div>
                            <p class="mt-4 font-display font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $match->slots->map(fn ($s) => $s->participant?->stage_name ?? '—')->implode(' vs ') }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $match->competition->name }} · {{ $match->stage?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Competitions --}}
        <section id="competitions" class="scroll-mt-24">
            <h2 class="font-display text-2xl font-bold">Compétitions en cours</h2>
            <p class="mt-1 text-slate-500">Suivez les résultats, étape par étape.</p>
            @if ($inProgress->isEmpty())
                <x-ui.empty icon="trophy" title="Aucune compétition en cours" class="mt-6" />
            @else
                <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($inProgress as $competition)
                        <a href="{{ route('fan.competitions.show', $competition) }}" class="group overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-slate-900/60 dark:ring-white/10">
                            <div class="relative h-20 bg-brand-600">
                                <x-ui.icon :name="$competition->discipline->icon()" class="absolute -right-2 -bottom-3 size-20 text-white/15" />
                                @if ($competition->voting_matches_count)
                                    <x-ui.badge tone="fuchsia" class="absolute top-3 left-3 !bg-white">{{ $competition->voting_matches_count }} vote(s) en cours</x-ui.badge>
                                @endif
                            </div>
                            <div class="p-5">
                                <p class="font-display font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $competition->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }} · {{ $competition->mode->label() }}</p>
                                <p class="mt-3 text-xs text-slate-400">{{ $competition->participants_count }} artiste(s)</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <h2 class="mt-14 font-display text-2xl font-bold">Inscriptions ouvertes</h2>
            <p class="mt-1 text-slate-500">Artistes : tentez votre chance.</p>
            @if ($open->isEmpty())
                <x-ui.empty icon="calendar" title="Aucune inscription ouverte pour le moment" class="mt-6" />
            @else
                <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($open as $competition)
                        <div class="flex flex-col rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10">
                            <div class="flex items-start justify-between">
                                <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-5" /></span>
                                <x-ui.badge tone="gray" :dot="false" :icon="$competition->mode->icon()">{{ $competition->mode->label() }}</x-ui.badge>
                            </div>
                            <p class="mt-4 font-display font-semibold">{{ $competition->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }}</p>
                            <p class="mt-3 text-xs text-slate-500">
                                {{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }} inscrits
                                @if ($competition->registration_ends_at) · jusqu'au {{ $competition->registration_ends_at->translatedFormat('d M') }}@endif
                                · {{ $competition->entry_fee ? $fmt($competition->entry_fee).' '.$competition->currency : 'Gratuit' }}
                            </p>
                            <x-ui.button class="mt-5 w-full" variant="soft" :href="$member ? route('artist.dashboard') : route('artist.register')" icon="user-plus">S'inscrire</x-ui.button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- How it works --}}
        <section id="fonctionnement" class="scroll-mt-24">
            <h2 class="text-center font-display text-2xl font-bold">Comment ça marche</h2>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['hand-thumb-up', 'Public', 'Créez votre compte avec votre numéro, vérifiez-le par SMS et votez une fois par battle. En salle, entrez le code affiché à l\'écran.', route('fan.register'), 'Créer mon compte'],
                    ['microphone', 'Artistes', 'Inscrivez-vous aux compétitions ouvertes. En ligne, envoyez votre vidéo ou votre son à chaque étape avant la date limite.', route('artist.register'), 'Devenir artiste'],
                    ['scale', 'Jury', 'Les organisateurs désignent leurs jurés. Chaque juré note les prestations critère par critère depuis son espace.', route('jury.login'), 'Espace jury'],
                ] as [$icon, $title, $text, $url, $cta])
                    <div class="rounded-2xl bg-slate-50 p-6 ring-1 ring-slate-900/5 dark:bg-white/[0.03] dark:ring-white/10">
                        <span class="grid size-12 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon :name="$icon" class="size-6" /></span>
                        <h3 class="mt-5 font-display text-lg font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $text }}</p>
                        <a href="{{ $url }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">{{ $cta }} <x-ui.icon name="arrow-right" variant="m" class="size-4" /></a>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Organizers --}}
        <section class="rounded-3xl bg-slate-900 px-6 py-12 text-white sm:px-12 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="max-w-xl">
                    <h2 class="font-display text-2xl font-bold">Vous organisez des battles ?</h2>
                    <p class="mt-2 text-white/70">Poules, brackets, jury, vote du public et soumissions en ligne : {{ $fmt($stats['organizers']) }} organisateur(s) pilotent déjà leurs compétitions sur Battle Game. Les espaces organisateurs sont ouverts par notre équipe.</p>
                </div>
                <x-ui.button size="lg" :href="route('login')" class="!bg-white !text-slate-900 hover:!bg-slate-100" icon="building-office-2">Espace organisateur</x-ui.button>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-100 dark:border-white/5">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-slate-500 sm:px-6">
            <span>© {{ date('Y') }} Battle Game</span>
            <nav class="flex flex-wrap gap-4">
                <a href="{{ route('fan.dashboard') }}" class="hover:text-slate-900 dark:hover:text-white">Compétitions</a>
                <a href="{{ route('fan.login') }}" class="hover:text-slate-900 dark:hover:text-white">Public</a>
                <a href="{{ route('artist.login') }}" class="hover:text-slate-900 dark:hover:text-white">Artistes</a>
                <a href="{{ route('jury.login') }}" class="hover:text-slate-900 dark:hover:text-white">Jury</a>
                <a href="{{ route('login') }}" class="hover:text-slate-900 dark:hover:text-white">Organisateurs</a>
            </nav>
        </div>
    </footer>
</body>
</html>
