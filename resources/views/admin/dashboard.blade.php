@php
    use App\Enums\CompetitionStatus;
    use App\Enums\OrganizerStatus;

    $competitionItems = collect(CompetitionStatus::cases())->map(fn ($s) => [
        'label' => $s->label(), 'value' => (int) ($competitionCounts[$s->value] ?? 0), 'href' => route('admin.competitions.index', ['status' => $s->value]),
    ])->all();
    $organizerItems = collect(OrganizerStatus::cases())->map(fn ($s) => [
        'label' => $s->label(), 'value' => (int) ($organizerCounts[$s->value] ?? 0), 'href' => route('admin.organizers.index', ['status' => $s->value]),
    ])->all();
    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
@endphp

<x-layouts.app title="Vue d'ensemble">
    <x-ui.page-header title="Vue d'ensemble" :breadcrumbs="['Console' => null]">
        <x-slot:description>
            <span>{{ now()->translatedFormat('l d F Y, H:i') }}</span>
            <span class="text-slate-300 dark:text-slate-600">•</span>
            <span>État global de la plateforme Battle Game.</span>
        </x-slot:description>
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.organizers.index', ['creer' => 1])" icon="plus">Nouvel organisateur</x-ui.button>
            @if ($organizerCounts[OrganizerStatus::Pending->value] ?? 0)
                <x-ui.button :href="route('admin.organizers.index', ['status' => 'en_attente'])" icon="clock">
                    {{ $organizerCounts[OrganizerStatus::Pending->value] }} organisateur(s) à vérifier
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Organisateurs" :value="$fmt($organizerCounts->sum())" icon="building-office-2" :hint="($organizerCounts[OrganizerStatus::Verified->value] ?? 0).' vérifié(s)'" />
        <x-ui.stat label="Compétitions" :value="$fmt($competitionCounts->sum())" icon="trophy" tone="blue" :hint="($competitionCounts[CompetitionStatus::InProgress->value] ?? 0).' en cours'" />
        <x-ui.stat label="Utilisateurs" :value="$fmt($stats['users'])" icon="users" tone="green" :hint="$fmt($stats['verified_users']).' numéro(s) vérifié(s) · '.$stats['backoffice_users'].' compte(s) back-office'" />
        <x-ui.stat label="Votes du public" :value="$fmt($stats['votes'])" icon="hand-thumb-up" tone="red" :hint="$fmt($stats['votes_24h']).' sur les dernières 24 h'" />
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10">
            <span class="grid size-10 place-items-center rounded-xl bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/10 dark:text-fuchsia-300"><x-ui.icon name="signal" class="size-5" /></span>
            <div><p class="font-display text-xl font-bold tabular-nums">{{ $stats['voting_matches'] }}</p><p class="text-xs text-slate-500">match(s) en vote en ce moment</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon name="microphone" class="size-5" /></span>
            <div><p class="font-display text-xl font-bold tabular-nums">{{ $fmt($stats['participants']) }}</p><p class="text-xs text-slate-500">inscription(s) d'artistes</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10">
            <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"><x-ui.icon name="scale" class="size-5" /></span>
            <div><p class="font-display text-xl font-bold tabular-nums">{{ $fmt($stats['judges']) }}</p><p class="text-xs text-slate-500">juré(s) désigné(s)</p></div>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <x-ui.card title="Compétitions par statut" icon="chart-bar" description="Nombre de compétitions et part du total">
            <x-bo.bar-list :items="$competitionItems" :total="$competitionCounts->sum()" />
        </x-ui.card>

        <x-ui.card title="Organisateurs par statut" icon="building-office-2" description="Nombre d'organisateurs et part du total">
            <x-bo.bar-list :items="$organizerItems" :total="$organizerCounts->sum()" />
        </x-ui.card>

        <x-ui.card title="À vérifier" icon="clock" :padding="false">
            <x-slot:actions><x-ui.button size="sm" variant="ghost" :href="route('admin.organizers.index', ['status' => 'en_attente'])" icon-right="arrow-right">Tout voir</x-ui.button></x-slot:actions>
            <div class="p-2" data-live="admin-pending-organizers">
                @forelse ($pendingOrganizers as $organizer)
                    @php($owner = $organizer->members->first()?->user)
                    <a href="{{ route('admin.organizers.show', $organizer) }}" class="flex items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                        <x-ui.avatar :name="$organizer->name" size="sm" square />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $organizer->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $owner?->email ?? '—' }} · {{ $organizer->created_at->diffForHumans() }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" variant="m" class="size-5 text-slate-300" />
                    </a>
                @empty
                    <div class="p-3"><x-ui.empty icon="check-badge" title="Rien à vérifier" description="Tous les organisateurs ont été traités." /></div>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-ui.card title="Compétitions en cours" icon="fire" class="xl:col-span-2" :padding="false">
            <x-slot:actions><x-ui.button size="sm" variant="ghost" :href="route('admin.competitions.index', ['status' => 'en_cours'])" icon-right="arrow-right">Tout voir</x-ui.button></x-slot:actions>
            <div class="p-5">
                @if ($liveCompetitions->isEmpty())
                    <x-ui.empty icon="fire" title="Aucune compétition en cours" />
                @else
                    <x-ui.table>
                        <x-slot:head><th>Compétition</th><th>Organisateur</th><th>Avancement</th><th class="!text-right">Votes</th></x-slot:head>
                        @foreach ($liveCompetitions as $competition)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.organizers.competitions.show', [$competition->organizer, $competition]) }}" class="font-semibold text-slate-900 hover:text-brand-600 dark:text-white">{{ $competition->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $competition->participants_count }} participant(s) · {{ $competition->discipline->label() }}</p>
                                </td>
                                <td><a href="{{ route('admin.organizers.show', $competition->organizer) }}" class="hover:text-brand-600">{{ $competition->organizer->name }}</a></td>
                                <td class="w-48">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-slate-100 dark:bg-white/5"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $competition->matches_count ? round($competition->decided_matches_count / $competition->matches_count * 100) : 0 }}%"></div></div>
                                        <span class="text-xs text-slate-500 tabular-nums">{{ $competition->decided_matches_count }}/{{ $competition->matches_count }}</span>
                                    </div>
                                </td>
                                <td class="text-right font-semibold tabular-nums">{{ $fmt($competition->public_votes_count) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Derniers inscrits" icon="user-plus" :padding="false">
            <x-slot:actions><x-ui.button size="sm" variant="ghost" :href="route('admin.users.index')" icon-right="arrow-right">Tout voir</x-ui.button></x-slot:actions>
            <div class="p-2">
                @foreach ($recentUsers as $recent)
                    <a href="{{ route('admin.users.show', $recent) }}" class="flex items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                        <x-ui.avatar :name="$recent->name" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $recent->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $recent->country?->flag }} {{ $recent->phone }}</span>
                        </span>
                        <span class="text-xs text-slate-400">{{ $recent->created_at->diffForHumans(short: true) }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    @php($hasSeeded = array_sum(array_slice($seeded['all'], 0, 3)) > 0)
    @if ($hasSeeded)
        <x-ui.card title="Données de test" icon="beaker" class="mt-6"
            description="Créées par les seeders locaux (compétitions de démo, faux artistes, jurés et fans). À vider avant la mise en service.">
            <x-slot:actions>
                <x-ui.button size="sm" variant="danger-soft" icon="trash" x-data x-on:click="$dispatch('open-modal', 'purge-demo-data')">Vider les données de test</x-ui.button>
            </x-slot:actions>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['Compétitions de démo', $seeded['demo']['competitions'], 'trophy'], ['Comptes générés', $seeded['demo']['users'], 'users'], ['Comptes de test', $seeded['all']['users'] - $seeded['demo']['users'], 'identification'], ['Organisateur de test', $seeded['all']['organizers'], 'building-office-2']] as [$label, $value, $icon])
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                        <dt class="flex items-center gap-1.5 text-xs text-slate-500"><x-ui.icon :name="$icon" variant="m" class="size-4 shrink-0" /><span class="truncate">{{ $label }}</span></dt>
                        <dd class="mt-1 font-display text-lg font-bold tabular-nums">{{ $fmt($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        @push('modals')
            <x-ui.modal name="purge-demo-data" title="Vider les données de test ?" icon="trash" danger
                description="Suppression définitive, sans retour possible. Les comptes réels, les super-admins et leurs compétitions ne sont jamais touchés.">
                <form method="POST" action="{{ route('admin.demo-data.destroy') }}" class="space-y-4" x-data="{ all: @js((bool) old('with_accounts')), typed: '' }">
                    @csrf @method('DELETE')
                    <input type="hidden" name="_form" value="purge-demo-data">

                    <ul class="space-y-1.5 rounded-xl bg-slate-50 p-3 text-sm text-slate-700 dark:bg-white/5 dark:text-slate-300">
                        <li class="flex gap-2"><x-ui.icon name="check" variant="m" class="mt-0.5 size-4 shrink-0 text-rose-500" /><span><strong>{{ $seeded['demo']['competitions'] }}</strong> compétition(s) de démo avec tout leur contenu : inscriptions, paiements simulés, matchs, votes, likes, notes, vidéos.</span></li>
                        <li class="flex gap-2"><x-ui.icon name="check" variant="m" class="mt-0.5 size-4 shrink-0 text-rose-500" /><span><strong x-text="all ? @js($fmt($seeded['all']['users'])) : @js($fmt($seeded['demo']['users']))">{{ $fmt($seeded['demo']['users']) }}</strong> compte(s) <span x-text="all ? 'générés et de test' : 'générés (faux artistes, jurés, fans)'">générés (faux artistes, jurés, fans)</span>.</span></li>
                        <li class="flex gap-2" x-show="all" x-cloak><x-ui.icon name="check" variant="m" class="mt-0.5 size-4 shrink-0 text-rose-500" /><span>L'organisateur de test et <strong>toutes</strong> ses compétitions ({{ $seeded['all']['competitions'] }} au total).</span></li>
                        @if ($seeded['all']['kept_users'])
                            <li class="flex gap-2 text-slate-500"><x-ui.icon name="shield-check" variant="m" class="mt-0.5 size-4 shrink-0" /><span>{{ $seeded['all']['kept_users'] }} compte(s) conservé(s) : encore utilisés dans une vraie compétition.</span></li>
                        @endif
                    </ul>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-slate-200 transition hover:bg-slate-50 dark:ring-white/10 dark:hover:bg-white/[0.03]">
                        <input type="checkbox" name="with_accounts" value="1" x-model="all" class="mt-0.5 size-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Supprimer aussi les comptes de test</span>
                            <span class="mt-0.5 block text-xs text-slate-500">L'organisateur de test et les comptes SEED_* (juré, artiste, public). Pour les recréer : <code>php artisan db:seed --class=LocalAccountsSeeder</code>.</span>
                        </span>
                    </label>

                    <x-ui.input name="confirmation" label="Saisissez VIDER pour confirmer" x-model="typed" autocomplete="off" />

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'purge-demo-data')">Annuler</x-ui.button>
                        <x-ui.button type="submit" variant="danger" icon="trash" x-bind:disabled="typed.trim().toUpperCase() !== 'VIDER'">Vider définitivement</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endpush
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::ADMIN]" />
</x-layouts.app>
