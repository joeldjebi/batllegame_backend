<x-layouts.app :title="$user->name">
    <x-ui.page-header :title="$user->name" :breadcrumbs="['Console' => route('admin.dashboard'), 'Utilisateurs' => route('admin.users.index'), $user->name => null]">
        <x-slot:leading><x-ui.avatar :name="$user->name" size="lg" /></x-slot:leading>
        <x-slot:description>
            @if ($user->isPlatformAdmin())<x-ui.badge tone="red" :dot="false" icon="shield-check">Super-admin</x-ui.badge>@endif
            @if ($user->phone_verified_at)
                <x-ui.badge tone="green" icon="check-badge" :dot="false">Numéro vérifié</x-ui.badge>
            @else
                <x-ui.badge tone="amber">Numéro non vérifié</x-ui.badge>
            @endif
            <span>Inscrit le {{ $user->created_at->translatedFormat('d M Y') }}</span>
        </x-slot:description>
    </x-ui.page-header>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Organisateurs" :value="$user->organizerMemberships->count()" icon="building-office-2" hint="Accès back-office" />
        <x-ui.stat label="Participations" :value="$user->participations->count()" icon="microphone" tone="blue" />
        <x-ui.stat label="Jury" :value="$user->judgeAssignments->count()" icon="scale" tone="green" />
        <x-ui.stat label="Votes" :value="$votes" icon="hand-thumb-up" tone="red" :hint="$devices.' appareil(s) distinct(s)'" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-ui.card title="Compte" icon="identification">
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'Nom' => $user->name,
                    'Téléphone' => ($user->country?->flag ? $user->country->flag.' ' : '').$user->phone,
                    'Email' => $user->email ?? '—',
                    'Pays' => $user->country?->name ?? '—',
                    'Numéro vérifié le' => $user->phone_verified_at?->translatedFormat('d M Y, H:i') ?? '—',
                    'Sessions mobiles actives' => $tokens,
                ] as $label => $value)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $label }}</dt><dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            @if ($user->organizerMemberships->isNotEmpty())
                <x-ui.card title="Organisateurs" icon="building-office-2" :padding="false">
                    <div class="p-2">
                        @foreach ($user->organizerMemberships as $membership)
                            <a href="{{ route('admin.organizers.show', $membership->organizer) }}" class="flex items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                                <x-ui.avatar :name="$membership->organizer->name" size="sm" square />
                                <span class="flex-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $membership->organizer->name }}</span>
                                <x-ui.badge :value="$membership->role" :dot="false" />
                                <x-ui.badge :value="$membership->organizer->status" />
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="Participations" icon="microphone" :padding="false">
                <div class="p-5">
                    @if ($user->participations->isEmpty())
                        <x-ui.empty icon="microphone" title="Aucune participation" />
                    @else
                        <x-ui.table>
                            <x-slot:head><th>Nom de scène</th><th>Compétition</th><th>Seed</th><th>Statut</th></x-slot:head>
                            @foreach ($user->participations as $participation)
                                <tr>
                                    <td class="font-semibold text-slate-900 dark:text-white">{{ $participation->stage_name }}</td>
                                    <td>
                                        @if ($participation->competition)
                                            <a href="{{ route('admin.organizers.competitions.show', [$participation->competition->organizer, $participation->competition]) }}" class="hover:text-brand-600">{{ $participation->competition->name }}</a>
                                            <p class="text-xs text-slate-500">{{ $participation->competition->organizer->name }}</p>
                                        @else
                                            <span class="text-slate-400">Compétition supprimée</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">{{ $participation->seed ?? '—' }}</td>
                                    <td><x-ui.badge :value="$participation->status" /></td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </div>
            </x-ui.card>

            @if ($user->judgeAssignments->isNotEmpty())
                <x-ui.card title="Jury" icon="scale" :padding="false">
                    <div class="p-2">
                        @foreach ($user->judgeAssignments->filter->competition as $assignment)
                            <a href="{{ route('admin.organizers.competitions.show', [$assignment->competition->organizer, $assignment->competition]) }}" class="flex items-center gap-3 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                                <span class="flex-1 text-sm"><span class="font-semibold text-slate-900 dark:text-white">{{ $assignment->competition->name }}</span> <span class="text-slate-500">· {{ $assignment->competition->organizer->name }}</span></span>
                                <x-ui.badge :value="$assignment->status" />
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="Derniers votes" icon="hand-thumb-up" :padding="false">
                <div class="p-5">
                    @if ($recentVotes->isEmpty())
                        <x-ui.empty icon="hand-thumb-up" title="Aucun vote" />
                    @else
                        <x-ui.table>
                            <x-slot:head><th>Pour</th><th>Compétition</th><th>Appareil</th><th>Date</th></x-slot:head>
                            @foreach ($recentVotes as $vote)
                                <tr>
                                    <td class="font-semibold text-slate-900 dark:text-white">{{ $vote->participant?->stage_name ?? '—' }}</td>
                                    <td>{{ $vote->competition?->name ?? '—' }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $vote->device_id ? Str::limit($vote->device_id, 16) : '—' }}</td>
                                    <td class="text-slate-500">{{ $vote->created_at->translatedFormat('d M Y, H:i') }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
