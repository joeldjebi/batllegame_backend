@props(['title' => null])

@php
    $portal = \App\Support\Portal::current();
    $user = auth($portal->guard)->user();
    $links = match ($portal->key) {
        'jury' => ['jury.dashboard' => ['Mes compétitions', 'trophy']],
        'artist' => ['artist.dashboard' => ['Mon espace', 'microphone'], 'fan.dashboard' => ['Voter', 'hand-thumb-up']],
        'fan' => ['fan.dashboard' => ['Compétitions', 'trophy'], 'artist.dashboard' => ['Espace artiste', 'microphone']],
    };
    $toasts = collect();
    if (session('status')) $toasts->push(['type' => 'success', 'message' => session('status')]);
    if ($errors->has('flow')) $toasts->push(['type' => 'error', 'message' => $errors->first('flow')]);
    elseif ($errors->any()) $toasts->push(['type' => 'error', 'message' => $errors->first()]);
@endphp

<!DOCTYPE html>
<html lang="fr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6d28d9">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $portal->title }} · Battle Game</title>
    <script>
        (() => {
            const mode = localStorage.getItem('theme') ?? 'light';
            if (mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl dark:border-white/5 dark:bg-slate-950/80">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
            <a href="{{ route($portal->home) }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon :name="$portal->icon" variant="s" class="size-5" /></span>
                <span class="leading-tight">
                    <span class="block font-display text-[15px] font-bold tracking-tight">Battle Game</span>
                    <span class="block text-[11px] font-medium tracking-wide text-slate-400 uppercase">{{ $portal->title }}</span>
                </span>
            </a>

            <nav class="ml-4 hidden items-center gap-1 sm:flex">
                @foreach ($links as $route => [$label, $icon])
                    <a href="{{ route($route) }}" @class([
                        'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' => request()->routeIs(Str::before($route, '.').'.*') && Str::before($route, '.') === $portal->key,
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' => ! (request()->routeIs(Str::before($route, '.').'.*') && Str::before($route, '.') === $portal->key),
                    ])><x-ui.icon :name="$icon" class="size-4" />{{ $label }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <x-realtime-status class="[&:not([hidden])]:inline-flex" />
                <x-ui.dropdown width="w-40">
                    <x-slot:trigger>
                        <button type="button" class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/10" title="Thème">
                            <x-ui.icon name="sun" class="size-5 dark:hidden" /><x-ui.icon name="moon" class="hidden size-5 dark:block" />
                        </button>
                    </x-slot:trigger>
                    <x-ui.dropdown-item icon="sun" x-on:click="$store.theme.set('light')">Clair</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="moon" x-on:click="$store.theme.set('dark')">Sombre</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="computer-desktop" x-on:click="$store.theme.set('system')">Système</x-ui.dropdown-item>
                </x-ui.dropdown>

                @if ($user)
                    <x-ui.dropdown width="w-64">
                        <x-slot:trigger>
                            <button type="button" class="flex items-center gap-2 rounded-xl p-1 pr-2 transition hover:bg-slate-100 dark:hover:bg-white/10">
                                <x-ui.avatar :name="$user->name" :src="$user->avatarUrl()" size="sm" />
                                <span class="hidden text-sm font-semibold sm:block">{{ $user->name }}</span>
                            </button>
                        </x-slot:trigger>
                        <div class="px-2.5 py-2">
                            <p class="text-sm font-semibold">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $user->phone }}</p>
                            @if ($portal->guard === 'member' && ! $user->hasVerifiedPhone())
                                <a href="{{ route('fan.verification.show') }}" class="mt-1 inline-block text-xs font-semibold text-amber-600">Vérifier mon numéro →</a>
                            @endif
                        </div>
                        <div class="my-1 h-px bg-slate-100 dark:bg-white/10"></div>
                        @if ($portal->key === 'jury')
                            <x-ui.dropdown-item :href="route('jury.password.edit')" icon="key">Mot de passe</x-ui.dropdown-item>
                        @else
                            <x-ui.dropdown-item :href="route('artist.profile.edit')" icon="user-circle">Mon profil</x-ui.dropdown-item>
                        @endif
                        <form method="POST" action="{{ route($portal->key.'.logout') }}">
                            @csrf
                            <x-ui.dropdown-item type="submit" icon="arrow-right-start-on-rectangle" danger>Déconnexion</x-ui.dropdown-item>
                        </form>
                    </x-ui.dropdown>
                @else
                    <x-ui.button size="sm" variant="secondary" :href="route($portal->key.'.login')">Connexion</x-ui.button>
                    @if ($portal->canRegister)<x-ui.button size="sm" :href="route($portal->key.'.register')">Créer un compte</x-ui.button>@endif
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 pt-6 pb-28 sm:px-6 sm:py-8">
        <div class="animate-slide-up" data-live="page">{{ $slot }}</div>
    </main>

    <x-portal.bottom-nav :portal="$portal->key" />

    {{-- Toasts: top-right on desktop, under the header on phones (the tab bar is at the bottom). --}}
    <div x-data="toaster(@js($toasts))" class="pointer-events-none fixed inset-x-0 top-16 z-[60] flex flex-col items-center gap-2 p-4 sm:left-auto sm:items-end sm:px-6">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible" x-transition.opacity class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10">
                <span :class="toast.type === 'error' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'" class="grid size-8 shrink-0 place-items-center rounded-full">
                    <template x-if="toast.type === 'error'"><x-ui.icon name="exclamation-triangle" variant="m" class="size-5" /></template>
                    <template x-if="toast.type !== 'error'"><x-ui.icon name="check" variant="m" class="size-5" /></template>
                </span>
                <p class="flex-1 pt-1 text-sm font-medium" x-text="toast.message"></p>
                <button type="button" x-on:click="dismiss(toast.id)" class="rounded-md p-1 text-slate-400"><x-ui.icon name="x-mark" variant="m" class="size-4" /></button>
            </div>
        </template>
    </div>

    {{-- Live region too: modals of rows added by a realtime refresh must exist. --}}
    <div data-live="modals">@stack('modals')</div>
</body>
</html>
