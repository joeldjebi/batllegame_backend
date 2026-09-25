<x-layouts.app title="Tableau de bord">
    <x-ui.page-header :title="'Bonjour, '.Str::before(auth()->user()->name, ' ').' 👋'">
        <x-slot:description>
            <span>{{ now()->translatedFormat('l d F Y') }}</span>
            <span class="text-slate-300 dark:text-slate-600">•</span>
            <span>Voici l'activité de vos organisateurs.</span>
        </x-slot:description>
    </x-ui.page-header>

    <div data-live="dashboard">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Compétitions" :value="$stats['competitions']" icon="trophy" :hint="$organizers->count().' organisateur(s)'" />
        <x-ui.stat label="En cours" :value="$stats['live']" icon="fire" tone="red" :hint="$stats['voting'].' match(s) en vote en ce moment'" />
        <x-ui.stat label="Inscriptions ouvertes" :value="$stats['registrations']" icon="user-plus" tone="blue" hint="Compétitions qui recrutent" />
        <x-ui.stat label="Participants" :value="number_format($stats['participants'], 0, ',', ' ')" icon="users" tone="green" hint="Tous organisateurs confondus" />
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <x-ui.card title="Compétitions récentes" description="Les dernières compétitions créées" icon="clock" class="xl:col-span-2" :padding="false">
            <div class="p-2">
                @forelse ($recentCompetitions as $competition)
                    <x-bo.competition-row :competition="$competition" />
                @empty
                    <div class="p-3">
                        <x-ui.empty icon="trophy" title="Aucune compétition" description="Ouvrez un organisateur pour créer votre première compétition." />
                    </div>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card title="Mes organisateurs" icon="building-office-2" :padding="false">
            <div class="space-y-1 p-2">
                @forelse ($organizers as $organizer)
                    <a href="{{ route('organizers.show', $organizer) }}" class="group flex items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                        <x-ui.avatar :name="$organizer->name" :src="$organizer->logoUrl()" square />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $organizer->name }}</span>
                                <x-ui.badge :value="$organizer->status" />
                            </span>
                            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs whitespace-nowrap text-slate-500 dark:text-slate-400">
                                <x-ui.badge :value="$organizer->membership->role" :dot="false" />
                                <span>{{ $organizer->competitions_count }} compétition(s)</span>
                                @if ($organizer->live_competitions_count)<span class="text-rose-500">{{ $organizer->live_competitions_count }} en cours</span>@endif
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="p-3">
                        <x-ui.empty icon="building-office-2" title="Aucun organisateur" description="Créez votre organisateur, ou demandez à un organisateur de vous ajouter à son équipe." />
                        @if (config('organizers.self_signup'))
                            <div class="mt-3 flex justify-center"><x-ui.button size="sm" :href="route('organizers.signup')" icon="plus">Créer un organisateur</x-ui.button></div>
                        @endif
                    </div>
                @endforelse
            </div>
        </x-ui.card>
    </div>
    </div>

    <x-realtime :channels="$organizers->map(fn ($o) => \App\Realtime\Channel::organizer($o->id))->all()" />
</x-layouts.app>
