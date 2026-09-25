@props(['title' => null])

@php
    $admin = request()->routeIs('admin.*');
    $user = auth()->user();
    $toasts = collect();
    if (session('status')) $toasts->push(['type' => 'success', 'message' => session('status')]);
    if ($errors->has('flow')) $toasts->push(['type' => 'error', 'message' => $errors->first('flow')]);
    elseif ($errors->any()) $toasts->push(['type' => 'error', 'message' => $errors->first()]);
@endphp

<!DOCTYPE html>
<html lang="fr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Battle Game{{ $admin ? ' Admin' : '' }}</title>
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
<body class="h-full bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" x-data="{ sidebar: false }">
    {{-- Mobile sidebar --}}
    <div x-show="sidebar" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="sidebar" x-transition.opacity class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" x-on:click="sidebar = false"></div>
        <div x-show="sidebar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 w-72">
            <x-bo.sidebar :admin="$admin" />
        </div>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-72 lg:flex-col">
        <x-bo.sidebar :admin="$admin" />
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur-xl sm:px-6 lg:px-10 dark:border-white/5 dark:bg-slate-950/70">
            <button type="button" class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-white/10" x-on:click="sidebar = true">
                <span class="sr-only">Ouvrir le menu</span><x-ui.icon name="bars-3" class="size-6" />
            </button>

            <div class="flex flex-1 items-center gap-3">
                @if ($admin)
                    <x-ui.badge tone="red" icon="shield-exclamation">Zone super-admin</x-ui.badge>
                @endif
                <x-realtime-status class="[&:not([hidden])]:inline-flex" />
            </div>

            {{-- Theme switcher --}}
            <x-ui.dropdown width="w-40">
                <x-slot:trigger>
                    <button type="button" class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white" title="Thème">
                        <x-ui.icon name="sun" class="size-5 dark:hidden" /><x-ui.icon name="moon" class="hidden size-5 dark:block" />
                    </button>
                </x-slot:trigger>
                <x-ui.dropdown-item icon="sun" x-on:click="$store.theme.set('light')">Clair</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="moon" x-on:click="$store.theme.set('dark')">Sombre</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="computer-desktop" x-on:click="$store.theme.set('system')">Système</x-ui.dropdown-item>
            </x-ui.dropdown>

            <span class="hidden h-6 w-px bg-slate-200 sm:block dark:bg-white/10"></span>

            {{-- User menu --}}
            <x-ui.dropdown width="w-64">
                <x-slot:trigger>
                    <button type="button" class="flex items-center gap-3 rounded-xl p-1 pr-2 transition hover:bg-slate-100 dark:hover:bg-white/10">
                        <x-ui.avatar :name="$user->name" size="sm" />
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $admin ? 'Super-admin' : $user->email }}</span>
                        </span>
                        <x-ui.icon name="chevron-down" variant="m" class="hidden size-4 text-slate-400 sm:block" />
                    </button>
                </x-slot:trigger>
                <div class="px-2.5 py-2">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                </div>
                <div class="my-1 h-px bg-slate-100 dark:bg-white/10"></div>
                <form method="POST" action="{{ $admin ? route('admin.logout') : route('logout') }}">
                    @csrf
                    <x-ui.dropdown-item type="submit" icon="arrow-right-start-on-rectangle" danger>Déconnexion</x-ui.dropdown-item>
                </form>
            </x-ui.dropdown>
        </header>

        <main class="px-4 py-8 sm:px-6 lg:px-10">
            <div class="mx-auto max-w-7xl animate-slide-up">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Toasts --}}
    {{-- Toasts: top-right, under the header. --}}
    <div x-data="toaster(@js($toasts))" class="pointer-events-none fixed inset-x-0 top-16 z-[60] flex flex-col items-center gap-2 p-4 sm:left-auto sm:items-end sm:px-6">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible" x-transition:enter="transform ease-out duration-300" x-transition:enter-start="-translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0" x-transition:enter-end="translate-x-0 translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10">
                <span :class="toast.type === 'error' ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300'" class="grid size-8 shrink-0 place-items-center rounded-full">
                    <template x-if="toast.type === 'error'"><x-ui.icon name="exclamation-triangle" variant="m" class="size-5" /></template>
                    <template x-if="toast.type !== 'error'"><x-ui.icon name="check" variant="m" class="size-5" /></template>
                </span>
                <p class="flex-1 pt-1 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="toast.message"></p>
                <button type="button" x-on:click="dismiss(toast.id)" class="rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><x-ui.icon name="x-mark" variant="m" class="size-4" /></button>
            </div>
        </template>
    </div>

    {{-- Live region too: modals of rows added by a realtime refresh must exist. --}}
    <div data-live="modals">@stack('modals')</div>
</body>
</html>
