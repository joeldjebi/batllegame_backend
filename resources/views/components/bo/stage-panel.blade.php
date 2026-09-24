@props(['stage', 'organizer', 'competition', 'canRun' => false])

@php
    use App\Enums\PerformanceStatus;
    use App\Enums\StageStatus;

    $online = $stage->isOnline();
    $participantIds = $stage->participantIds();
    $performances = $stage->performances->whereIn('participant_id', $participantIds);
    $submitted = $performances->where('status', '!=', PerformanceStatus::Rejected)->count();
    $toReview = $performances->whereIn('status', [PerformanceStatus::Pending, PerformanceStatus::Processing]);
    $modal = 'schedule-stage-'.$stage->id;
    // Later stages wait for the winners of the previous one.
    $ready = ! $stage->matches()->where('status', \App\Enums\MatchStatus::Scheduled)->whereHas('slots', fn ($q) => $q->whereNull('participant_id'))->exists();
    $fmt = fn ($date) => $date?->translatedFormat('d M, H:i');
    // Group phase: submissions shown per group, with who has not sent yet.
    $groups = $stage->phase->type === \App\Enums\PhaseType::Groups
        ? $stage->matches->whereNotNull('group_id')->sortBy('bracket_position')->map(fn ($match) => [
            'name' => $match->group?->name ?? 'Poule',
            'members' => $match->slots->pluck('participant')->filter()->values(),
        ])->values()->all()
        : [];
@endphp

<div class="rounded-xl bg-slate-50/80 p-4 ring-1 ring-slate-900/5 dark:bg-white/[0.02] dark:ring-white/10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-brand-600 shadow-soft ring-1 ring-slate-900/5 dark:bg-white/10 dark:text-brand-300">
                <x-ui.icon :name="$online ? 'cloud-arrow-up' : 'map-pin'" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                    Étape {{ $stage->number }} · {{ $stage->name }} <x-ui.badge :value="$stage->status" />
                </p>
                <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-slate-500">
                    <span>{{ $online ? 'En ligne' : 'Présentiel' }}</span>
                    @if ($online)<span>Limite : {{ $fmt($stage->submission_deadline) ?? 'non définie' }}</span>@endif
                    <span>Vote : {{ $fmt($stage->voting_opens_at) ?? ($online ? 'après la limite' : 'en direct') }} → {{ $fmt($stage->voting_closes_at) ?? 'clôture manuelle' }}</span>
                    @if ($stage->deliberation_minutes > 0)<span>Délibération : {{ \Carbon\CarbonInterval::minutes($stage->deliberation_minutes)->cascade()->forHumans(short: true) }} après le vote</span>@endif
                </p>
            </div>
        </div>

        @if ($canRun && $stage->status !== StageStatus::Closed)
            <div class="flex flex-wrap gap-2">
                <x-ui.button size="sm" variant="secondary" icon="calendar-days" x-data x-on:click="$dispatch('open-modal', '{{ $modal }}')">Calendrier</x-ui.button>
                @if ($stage->status === StageStatus::Pending && ! $ready)
                    <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-slate-500"><x-ui.icon name="clock" variant="m" class="size-4" /> En attente de l'étape précédente</span>
                @endif
                @if ($online && $stage->status === StageStatus::Pending && $ready)
                    <x-ui.confirm :action="route('organizers.competitions.stages.open-submissions', [$organizer, $competition, $stage])" :danger="false" icon="cloud-arrow-up"
                        title="Ouvrir les soumissions ?" message="Les participants de l'étape pourront envoyer leur média jusqu'à la date limite. Sans soumission à la date limite : forfait." confirm="Ouvrir">
                        <x-ui.button size="sm" icon="cloud-arrow-up">Ouvrir les soumissions</x-ui.button>
                    </x-ui.confirm>
                @endif
                @if (($online && $stage->status === StageStatus::Submissions && $stage->isDeadlinePassed()) || (! $online && $stage->status === StageStatus::Pending && $ready))
                    <x-ui.confirm :action="route('organizers.competitions.stages.open-voting', [$organizer, $competition, $stage])" :danger="false" icon="play"
                        title="Ouvrir le vote de toute l'étape ?" message="Le public et le jury pourront voter et noter tous les matchs de l'étape." confirm="Ouvrir le vote">
                        <x-ui.button size="sm" icon="play">Ouvrir le vote</x-ui.button>
                    </x-ui.confirm>
                @endif
            </div>
        @endif
    </div>

    @if ($online && ($stage->status === StageStatus::Submissions || $performances->isNotEmpty()))
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                <p class="text-xs text-slate-500">Soumissions reçues</p>
                <p class="font-display text-lg font-bold tabular-nums">{{ $submitted }} / {{ count($participantIds) }}</p>
            </div>
            <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                <p class="text-xs text-slate-500">À valider</p>
                <p @class(['font-display text-lg font-bold tabular-nums', 'text-amber-600' => $toReview->isNotEmpty()])>{{ $toReview->count() }}</p>
            </div>
            <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                <p class="text-xs text-slate-500">Rejetées</p>
                <p class="font-display text-lg font-bold tabular-nums">{{ $performances->where('status', PerformanceStatus::Rejected)->count() }}</p>
            </div>
        </div>

        @if ($performances->isNotEmpty())
            <div x-data="{ open: @js($toReview->isNotEmpty()) }" class="mt-3">
                <button type="button" x-on:click="open = ! open" class="flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">
                    <x-ui.icon name="chevron-down" variant="m" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                    <span x-text="open ? 'Masquer les soumissions' : 'Voir les {{ $performances->count() }} soumission(s){{ $groups ? ' par poule' : '' }}'"></span>
                </button>
                @if ($groups)
                    <div x-show="open" x-collapse x-cloak class="mt-3 space-y-5">
                        @foreach ($groups as ['name' => $groupName, 'members' => $members])
                            @php
                                $groupPerformances = $performances->whereIn('participant_id', $members->pluck('id'));
                                $missing = $members->whereNotIn('id', $groupPerformances->pluck('participant_id'));
                                $pendingInGroup = $groupPerformances->whereIn('status', [PerformanceStatus::Pending, PerformanceStatus::Processing])->count();
                            @endphp
                            <section>
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <h4 class="font-display text-sm font-bold text-slate-900 dark:text-white">{{ $groupName }}</h4>
                                    <x-ui.badge tone="gray" :dot="false">{{ $groupPerformances->count() }} / {{ $members->count() }} envoyée(s)</x-ui.badge>
                                    @if ($pendingInGroup)<x-ui.badge tone="amber" :dot="false">{{ $pendingInGroup }} à valider</x-ui.badge>@endif
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach ($groupPerformances->sortBy(fn ($p) => $p->status === PerformanceStatus::Pending ? 0 : 1) as $performance)
                                        <x-bo.performance-card :performance="$performance" :organizer="$organizer" :competition="$competition" :can-run="$canRun" />
                                    @endforeach
                                    @foreach ($missing as $member)
                                        <div class="flex items-center gap-3 rounded-lg border border-dashed border-slate-200 p-3 text-sm text-slate-500 dark:border-white/10">
                                            <x-ui.avatar :name="$member->stage_name" :src="$member->user?->avatarUrl()" size="sm" />
                                            <span class="min-w-0 flex-1 truncate font-medium text-slate-700 dark:text-slate-200">{{ $member->stage_name }}</span>
                                            <span class="text-xs">{{ $stage->isDeadlinePassed() ? 'Forfait : rien reçu' : 'Pas encore envoyée' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <div x-show="open" x-collapse x-cloak class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach ($performances->sortBy(fn ($p) => $p->status === PerformanceStatus::Pending ? 0 : 1) as $performance)
                            <x-bo.performance-card :performance="$performance" :organizer="$organizer" :competition="$competition" :can-run="$canRun" />
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>

@if ($canRun && $stage->status !== StageStatus::Closed)
    @push('modals')
        <x-ui.modal :name="$modal" :title="'Calendrier · '.$stage->name" icon="calendar-days" :description="$online ? 'Sans soumission à la date limite, le participant perd par forfait.' : 'En présentiel, vous pouvez aussi ouvrir le vote match par match, en direct.'">
            <form method="POST" action="{{ route('organizers.competitions.stages.update', [$organizer, $competition, $stage]) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="{{ $modal }}"> @method('PUT')
                @if ($online)
                    <x-ui.input name="submission_deadline" type="datetime-local" label="Date limite de soumission" :value="$stage->submission_deadline" required />
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="voting_opens_at" type="datetime-local" label="Ouverture du vote" :value="$stage->voting_opens_at" :hint="$online ? 'Vide = dès la date limite.' : null" />
                    <x-ui.input name="voting_closes_at" type="datetime-local" label="Fin du vote du public" :value="$stage->voting_closes_at" hint="Vide = clôture manuelle." />
                </div>
                <x-ui.input name="deliberation_minutes" type="number" min="0" max="10080" label="Délibération du jury" :value="$stage->deliberation_minutes" suffix="min"
                    hint="Temps laissé au jury après la fin du vote (ex. 1440 = 24 h). Les matchs se clôturent ensuite automatiquement." />
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $modal }}')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="check">Enregistrer</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endpush
@endif
