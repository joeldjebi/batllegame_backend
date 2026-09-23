@php
    use App\Enums\MatchStatus;
    use App\Enums\ParticipantStatus;
    use App\Enums\PhaseType;
    use App\Enums\VoteMode;

    $fmt = fn ($n) => number_format($n, 0, ',', ' ');
    $participants = $competition->participants->sortBy(fn ($p) => [$p->seed ?? PHP_INT_MAX, Str::lower($p->stage_name)]);
    $matches = $competition->phases->flatMap->matches;
    $played = $matches->whereIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count();
    $settings = $competition->settings;
    $byStatus = $participants->countBy(fn ($p) => $p->status->value);
@endphp

<x-layouts.app :title="$competition->name">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Console' => route('admin.dashboard'), 'Compétitions' => route('admin.competitions.index'), $competition->name => null]">
        <x-slot:leading>
            <span class="hidden size-14 shrink-0 place-items-center rounded-2xl bg-brand-600 text-white shadow-lift sm:grid"><x-ui.icon :name="$competition->discipline->icon()" class="size-7" /></span>
        </x-slot:leading>
        <x-slot:description>
            <x-ui.badge :value="$competition->status" />
            <a href="{{ route('admin.organizers.show', $organizer) }}" class="inline-flex items-center gap-1 hover:text-brand-600"><x-ui.icon name="building-office-2" variant="m" class="size-4" />{{ $organizer->name }}</a>
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->discipline->icon()" variant="m" class="size-4" />{{ $competition->discipline->label() }}</span>
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-4" />{{ $competition->mode->label() }}</span>
            <span class="font-mono text-xs">{{ $competition->slug }}</span>
        </x-slot:description>
        <x-slot:actions>
            <x-ui.badge tone="gray" icon="eye" :dot="false">Lecture seule</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <x-bo.lifecycle :competition="$competition" class="mb-8" />

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Participants" :value="$participants->count().($competition->max_participants ? ' / '.$competition->max_participants : '')" icon="users"
            :progress="$competition->max_participants ? $participants->count() / $competition->max_participants * 100 : null" :hint="($byStatus[ParticipantStatus::Validated->value] ?? 0).' validé(s) · '.($byStatus[ParticipantStatus::Registered->value] ?? 0).' en attente'" />
        <x-ui.stat label="Matchs joués" :value="$played.' / '.$matches->count()" icon="bolt" tone="blue" :progress="$matches->count() ? $played / $matches->count() * 100 : null" :hint="$stats['voting_matches'].' vote(s) en cours'" />
        <x-ui.stat label="Votes du public" :value="$fmt($stats['votes'])" icon="hand-thumb-up" tone="red" :hint="$fmt($stats['voters']).' votant(s) · '.$fmt($stats['votes_24h']).' sur 24 h'" />
        <x-ui.stat label="Notes du jury" :value="$fmt($stats['jury_scores'])" icon="scale" tone="green" :hint="$competition->judges->count().' juré(s) · '.$competition->criteria->count().' critère(s)'" />
    </div>

    <x-ui.tabs key="admin-competition" :tabs="[
        'overview' => ['label' => 'Aperçu', 'icon' => 'home'],
        'phases' => ['label' => 'Phases & matchs', 'icon' => 'trophy', 'count' => $competition->phases->count()],
        'participants' => ['label' => 'Participants', 'icon' => 'users', 'count' => $participants->count()],
        'jury' => ['label' => 'Jury & critères', 'icon' => 'scale', 'count' => $competition->judges->count()],
    ]">
        <x-ui.tab-panel name="overview">
            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card title="Déroulé" icon="queue-list" class="xl:col-span-2">
                    @forelse ($competition->phases as $phase)
                        @php($done = $phase->matches->whereIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count())
                        <div class="flex gap-4 border-b border-slate-100 py-4 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$phase->type->icon()" class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-white">Phase {{ $phase->position }} · {{ $phase->type->label() }}</p>
                                    <x-ui.badge :value="$phase->status" />
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $phase->effectiveMode()->label() }} · {{ $phase->rules->voteMode->label() }}@if ($phase->rules->voteMode === VoteMode::Mixed) ({{ $phase->rules->juryWeight }} % / {{ $phase->rules->publicWeight }} %)@endif
                                    · {{ $phase->rules->rounds }} × {{ $phase->rules->turnDuration }} s
                                    @if ($phase->started_at) · démarrée le {{ $phase->started_at->translatedFormat('d M Y, H:i') }}@endif
                                </p>
                                @if ($phase->matches->isNotEmpty())
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="h-2 flex-1 rounded-full bg-slate-100 dark:bg-white/5"><div class="h-2 rounded-full bg-brand-600" style="width: {{ round($done / $phase->matches->count() * 100) }}%"></div></div>
                                        <span class="text-xs text-slate-500 tabular-nums">{{ $done }}/{{ $phase->matches->count() }} matchs</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-ui.empty icon="queue-list" title="Aucune phase configurée" />
                    @endforelse
                </x-ui.card>

                <x-ui.card title="Configuration" icon="cog-6-tooth">
                    <dl class="space-y-3 text-sm">
                        @foreach ([
                            'Créée par' => $competition->creator?->name ?? '—',
                            'Créée le' => $competition->created_at->translatedFormat('d M Y'),
                            'Fin des inscriptions' => $competition->registration_ends_at?->translatedFormat('d M Y, H:i') ?? '—',
                            'Frais' => $competition->entry_fee ? $fmt($competition->entry_fee).' '.$competition->currency : 'Gratuit',
                            'Validation des inscriptions' => $settings->registrationRequiresApproval ? 'Manuelle' : 'Automatique',
                            'Vote du public' => $settings->publicVotingEnabled ? 'Activé' : 'Désactivé',
                            'Résultats en direct' => $settings->showLiveResults ? 'Oui' : 'Après clôture',
                            'Votes max. par appareil' => $settings->maxVotesPerDevice ?? 'Illimité',
                            'Fuseau horaire' => $settings->timezone,
                        ] as $label => $value)
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $label }}</dt><dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </x-ui.card>
            </div>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="phases" class="space-y-6">
            @forelse ($competition->phases as $phase)
                <x-ui.card :title="'Phase '.$phase->position.' · '.$phase->type->label()" :icon="$phase->type->icon()">
                    <x-slot:actions><x-ui.badge :value="$phase->status" /></x-slot:actions>
                    @if (! $phase->isFrozen())
                        <x-ui.empty icon="clock" title="Pas encore démarrée" description="Les matchs seront générés au démarrage par l'organisateur." />
                    @elseif ($phase->type === PhaseType::Groups)
                        <div class="grid gap-5 lg:grid-cols-2">
                            @foreach ($phase->groups as $group)
                                <x-bo.standings :group="$group" :qualifiers="$phase->qualifiers_per_group" />
                            @endforeach
                        </div>
                    @else
                        <x-bo.bracket :phase="$phase" :organizer="$organizer" :competition="$competition" :can-run="false" />
                    @endif
                </x-ui.card>
            @empty
                <x-ui.empty icon="trophy" title="Aucune phase" />
            @endforelse
        </x-ui.tab-panel>

        <x-ui.tab-panel name="participants">
            <x-ui.card title="Participants" icon="users" x-data="{ search: '' }">
                <x-slot:actions>
                    <div class="relative">
                        <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" x-model="search" placeholder="Rechercher…" class="w-48 rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                    </div>
                </x-slot:actions>
                @if ($participants->isEmpty())
                    <x-ui.empty icon="user-plus" title="Aucune inscription" />
                @else
                    <x-ui.table>
                        <x-slot:head><th>Seed</th><th>Artiste</th><th>Compte</th><th>Statut</th><th>Inscrit le</th><th></th></x-slot:head>
                        @foreach ($participants as $participant)
                            <tr x-show="@js(Str::lower($participant->stage_name.' '.$participant->user->name.' '.$participant->user->phone)).includes(search.toLowerCase())">
                                <td class="tabular-nums text-slate-500">{{ $participant->seed ?? '—' }}</td>
                                <td><div class="flex items-center gap-3"><x-ui.avatar :name="$participant->stage_name" size="sm" /><span class="font-semibold text-slate-900 dark:text-white">{{ $participant->stage_name }}</span></div></td>
                                <td><p>{{ $participant->user->name }}</p><p class="text-xs text-slate-500">{{ $participant->user->phone }}</p></td>
                                <td><x-ui.badge :value="$participant->status" /></td>
                                <td class="text-slate-500">{{ $participant->created_at->translatedFormat('d M Y') }}</td>
                                <td class="text-right"><x-ui.button size="sm" variant="ghost" :href="route('admin.users.show', $participant->user)" icon="eye"><span class="sr-only">Voir le compte</span></x-ui.button></td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="jury">
            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card title="Jury" icon="scale">
                    @forelse ($competition->judges as $judge)
                        <a href="{{ route('admin.users.show', $judge->user) }}" class="flex items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0 hover:text-brand-600 dark:border-white/5">
                            <x-ui.avatar :name="$judge->user->name" size="sm" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $judge->user->name }}</span><span class="text-xs text-slate-500">{{ $judge->user->phone }}</span></span>
                            <x-ui.badge :value="$judge->status" />
                        </a>
                    @empty
                        <x-ui.empty icon="scale" title="Aucun juré" />
                    @endforelse
                </x-ui.card>
                <x-ui.card title="Critères de notation" icon="adjustments-horizontal">
                    @php($totalWeight = $competition->criteria->sum('weight') ?: 1)
                    @forelse ($competition->criteria as $criterion)
                        <div class="border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $criterion->name }}</span>
                                <span class="text-slate-500">sur {{ $criterion->max_points }} · <span class="font-semibold text-slate-900 tabular-nums dark:text-white">{{ round($criterion->weight / $totalWeight * 100) }} %</span></span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-white/5"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $criterion->weight / $totalWeight * 100 }}%"></div></div>
                        </div>
                    @empty
                        <x-ui.empty icon="adjustments-horizontal" title="Aucun critère" />
                    @endforelse
                </x-ui.card>
            </div>
        </x-ui.tab-panel>
    </x-ui.tabs>
</x-layouts.app>
