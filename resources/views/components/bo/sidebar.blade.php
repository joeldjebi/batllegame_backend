@props(['admin' => false])

@php
    $currentOrganizer = request()->route('organizer');
    $currentCompetition = request()->route('competition');
@endphp

<div class="flex h-full flex-col gap-y-6 overflow-y-auto border-r border-slate-200/70 bg-white px-4 py-5 dark:border-white/5 dark:bg-slate-900">
    <a href="{{ $admin ? route('admin.organizers.index') : route('dashboard') }}" class="px-2"><x-bo.logo :admin="$admin" /></a>

    <nav class="flex flex-1 flex-col gap-6">
        @if ($admin)
            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Plateforme</p>
                <x-bo.nav-link :href="route('admin.organizers.index')" icon="building-office-2" :active="request()->routeIs('admin.organizers.*') && ! request('status')" :count="$adminCounts['all'] ?? null">Organisateurs</x-bo.nav-link>
                <x-bo.nav-link :href="route('admin.organizers.index', ['status' => 'en_attente'])" icon="clock" :active="request('status') === 'en_attente'" :count="$adminCounts['en_attente'] ?? null">À vérifier</x-bo.nav-link>
                <x-bo.nav-link :href="route('admin.organizers.index', ['status' => 'verifie'])" icon="check-badge" :active="request('status') === 'verifie'">Vérifiés</x-bo.nav-link>
                <x-bo.nav-link :href="route('admin.organizers.index', ['status' => 'suspendu'])" icon="no-symbol" :active="request('status') === 'suspendu'">Suspendus</x-bo.nav-link>
            </div>
        @else
            <div class="space-y-1">
                <x-bo.nav-link :href="route('dashboard')" icon="squares-2x2" :active="request()->routeIs('dashboard')">Tableau de bord</x-bo.nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Mes organisateurs</p>
                @forelse ($navOrganizers as $navOrganizer)
                    @php($isCurrent = $currentOrganizer?->is($navOrganizer))
                    <div x-data="{ open: @js($isCurrent) }">
                        <a href="{{ route('organizers.show', $navOrganizer) }}" @class([
                            'group flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-medium transition',
                            'bg-slate-100 text-slate-900 dark:bg-white/5 dark:text-white' => $isCurrent,
                            'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $isCurrent,
                        ])>
                            <x-ui.avatar :name="$navOrganizer->name" :src="$navOrganizer->logo_path ? Storage::url($navOrganizer->logo_path) : null" size="xs" square />
                            <span class="flex-1 truncate">{{ $navOrganizer->name }}</span>
                            @unless ($navOrganizer->isVerified())
                                <span @class(['size-2 rounded-full', 'bg-amber-400' => ! $navOrganizer->isSuspended(), 'bg-rose-500' => $navOrganizer->isSuspended()]) title="{{ $navOrganizer->status->label() }}"></span>
                            @endunless
                        </a>
                        @if ($isCurrent)
                            <div class="mt-1 ml-5 space-y-0.5 border-l border-slate-200 pl-3 dark:border-white/10">
                                <a href="{{ route('organizers.show', $navOrganizer) }}#competitions" class="block rounded-lg px-2 py-1.5 text-[13px] text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">Compétitions</a>
                                @if ($currentCompetition)
                                    <a href="{{ route('organizers.competitions.show', [$navOrganizer, $currentCompetition]) }}" class="flex items-center gap-1.5 rounded-lg bg-brand-50 px-2 py-1.5 text-[13px] font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">
                                        <x-ui.icon name="trophy" variant="m" class="size-3.5" /><span class="truncate">{{ $currentCompetition->name }}</span>
                                    </a>
                                @endif
                                <a href="{{ route('organizers.show', $navOrganizer) }}#members" class="block rounded-lg px-2 py-1.5 text-[13px] text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">Membres</a>
                                <a href="{{ route('organizers.show', $navOrganizer) }}#profile" class="block rounded-lg px-2 py-1.5 text-[13px] text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">Profil</a>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-3 text-sm text-slate-400">Aucun organisateur pour l'instant.</p>
                @endforelse
            </div>
        @endif

        <div class="mt-auto">
            @if ($admin)
                <div class="rounded-2xl bg-slate-900 p-4 text-white ring-1 ring-white/10 dark:bg-white/5">
                    <div class="flex items-center gap-2 text-sm font-semibold"><x-ui.icon name="lock-closed" class="size-4 text-emerald-400" /> Session sécurisée</div>
                    <p class="mt-1 text-xs text-white/60">Déconnexion automatique après {{ config('admin.idle_timeout') }} min d'inactivité.</p>
                </div>
            @else
                <div class="relative overflow-hidden rounded-2xl bg-brand-gradient p-4 text-white shadow-lift">
                    <div class="bg-grid absolute inset-0 opacity-20"></div>
                    <div class="relative">
                        <x-ui.icon name="sparkles" class="size-5" />
                        <p class="mt-2 text-sm font-semibold">Lancez votre prochaine battle</p>
                        <p class="mt-1 text-xs text-white/80">Poules, brackets, jury et vote du public : tout est prêt.</p>
                    </div>
                </div>
            @endif
        </div>
    </nav>
</div>
