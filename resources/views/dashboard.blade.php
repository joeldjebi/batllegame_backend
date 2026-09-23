<x-layouts.app title="Tableau de bord">
    <x-ui.page-header :title="'Bonjour, '.Str::before(auth()->user()->name, ' ').' 👋'">
        <x-slot:description>
            <span>{{ now()->translatedFormat('l d F Y') }}</span>
            <span class="text-slate-300 dark:text-slate-600">•</span>
            <span>Voici l'activité de vos organisateurs.</span>
        </x-slot:description>
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-organizer')">Nouvel organisateur</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

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
                        <x-ui.avatar :name="$organizer->name" :src="$organizer->logo_path ? Storage::url($organizer->logo_path) : null" square />
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
                        <x-ui.empty icon="building-office-2" title="Aucun organisateur" description="Créez votre structure (label, association, salle…) pour commencer.">
                            <x-ui.button size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-organizer')">Créer</x-ui.button>
                        </x-ui.empty>
                    </div>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal name="create-organizer" title="Nouvel organisateur" description="Il sera vérifié par la plateforme avant de pouvoir ouvrir des inscriptions." icon="building-office-2" :show="$errors->hasAny(['name', 'city', 'logo'])">
        <form method="POST" action="{{ route('organizers.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <x-ui.input name="name" label="Nom de la structure" placeholder="Ex. Abidjan Battle League" required />
            <x-ui.input name="city" label="Ville" icon="map-pin" placeholder="Abidjan" />
            <x-ui.textarea name="description" label="Description" rows="3" />
            <x-ui.field label="Logo" hint="PNG ou JPG, 2 Mo maximum.">
                <input type="file" name="logo" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
            </x-ui.field>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'create-organizer')">Annuler</x-ui.button>
                <x-ui.button type="submit" icon="check">Créer l'organisateur</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.app>
