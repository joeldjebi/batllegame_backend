@php($fmt = fn ($n) => number_format($n, 0, ',', ' '))

<x-layouts.app :title="$organizer->name">
    <x-ui.page-header :title="$organizer->name" :breadcrumbs="['Console' => route('admin.dashboard'), 'Organisateurs' => route('admin.organizers.index'), $organizer->name => null]">
        <x-slot:leading>
            <x-ui.avatar :name="$organizer->name" :src="$organizer->logoUrl()" size="lg" square />
        </x-slot:leading>
        <x-slot:description>
            <x-ui.badge :value="$organizer->status" />
            @if ($organizer->locationLabel())<span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" variant="m" class="size-4" />{{ $organizer->locationLabel() }}</span>@endif
            <span class="inline-flex items-center gap-1"><x-ui.icon name="calendar" variant="m" class="size-4" />Créé le {{ $organizer->created_at->translatedFormat('d M Y') }}</span>
            <span class="font-mono text-xs">{{ $organizer->slug }}</span>
        </x-slot:description>
        <x-slot:actions>
            <x-admin.organizer-actions :organizer="$organizer" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Compétitions" :value="$competitions->count()" icon="trophy" :hint="$competitions->where('status', \App\Enums\CompetitionStatus::InProgress)->count().' en cours'" />
        <x-ui.stat label="Participants" :value="$fmt($stats['participants'])" icon="users" tone="green" hint="Toutes compétitions confondues" />
        <x-ui.stat label="Matchs" :value="$fmt($stats['matches'])" icon="bolt" tone="blue" />
        <x-ui.stat label="Votes du public" :value="$fmt($stats['votes'])" icon="hand-thumb-up" tone="red" />
    </div>

    <x-ui.tabs key="admin-organizer" :tabs="[
        'competitions' => ['label' => 'Compétitions', 'icon' => 'trophy', 'count' => $competitions->count()],
        'members' => ['label' => 'Membres', 'icon' => 'user-group', 'count' => $members->count()],
        'profile' => ['label' => 'Fiche', 'icon' => 'identification'],
    ]">
        <x-ui.tab-panel name="competitions">
            <x-ui.card :padding="false">
                <div class="p-5">
                    @if ($competitions->isEmpty())
                        <x-ui.empty icon="trophy" title="Aucune compétition" description="Cet organisateur n'a pas encore créé de compétition." />
                    @else
                        <x-ui.table>
                            <x-slot:head><th>Compétition</th><th>Statut</th><th>Participants</th><th>Phases · matchs</th><th class="!text-right">Votes</th><th></th></x-slot:head>
                            @foreach ($competitions as $competition)
                                <tr @class(['opacity-60' => $competition->trashed()])>
                                    <td>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $competition->name }} @if ($competition->trashed())<x-ui.badge tone="red" :dot="false">Supprimée</x-ui.badge>@endif</p>
                                        <p class="text-xs text-slate-500">{{ $competition->discipline->label() }} · {{ $competition->mode->label() }} · créée le {{ $competition->created_at->translatedFormat('d M Y') }}</p>
                                    </td>
                                    <td><x-ui.badge :value="$competition->status" /></td>
                                    <td class="tabular-nums">{{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }}</td>
                                    <td class="tabular-nums text-slate-500">{{ $competition->phases_count }} · {{ $competition->matches_count }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ $fmt($competition->public_votes_count) }}</td>
                                    <td class="text-right">
                                        @unless ($competition->trashed())
                                            <x-ui.button size="sm" variant="secondary" :href="route('admin.organizers.competitions.show', [$organizer, $competition])" icon="eye">Détails</x-ui.button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </div>
            </x-ui.card>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="members">
            <x-ui.card title="Équipe" icon="user-group" description="Comptes back-office rattachés à cet organisateur">
                <x-ui.table>
                    <x-slot:head><th>Membre</th><th>Contact</th><th>Rôle</th><th>Membre depuis</th><th></th></x-slot:head>
                    @foreach ($members as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$member->user->name" size="sm" />
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ $member->user->name }}</span>
                                </div>
                            </td>
                            <td><p>{{ $member->user->email ?? '—' }}</p><p class="text-xs text-slate-500">{{ $member->user->country?->flag }} {{ $member->user->phone }}</p></td>
                            <td><x-ui.badge :value="$member->role" /></td>
                            <td class="text-slate-500">{{ $member->created_at?->translatedFormat('d M Y') }}</td>
                            <td class="text-right"><x-ui.button size="sm" variant="ghost" :href="route('admin.users.show', $member->user)" icon="eye"><span class="sr-only">Voir le compte</span></x-ui.button></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="profile">
            <x-ui.card title="Fiche de l'organisateur" icon="identification" class="max-w-3xl">
                <dl class="grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Nom</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $organizer->name }}</dd></div>
                    <div><dt class="text-slate-500">Identifiant (slug)</dt><dd class="mt-1 font-mono text-slate-900 dark:text-white">{{ $organizer->slug }}</dd></div>
                    <div><dt class="text-slate-500">Ville</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $organizer->locationLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Offre</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $organizer->plan->label() }}</dd></div>
                    <div><dt class="text-slate-500">Statut</dt><dd class="mt-1"><x-ui.badge :value="$organizer->status" /></dd></div>
                    <div><dt class="text-slate-500">Vérifié le</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $organizer->verified_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Description</dt><dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $organizer->description ?: '—' }}</dd></div>
                </dl>
            </x-ui.card>
        </x-ui.tab-panel>
    </x-ui.tabs>
</x-layouts.app>
