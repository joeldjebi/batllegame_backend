@props(['competition', 'organizer', 'canUpdate' => false, 'canRun' => false])

@php
    use App\Enums\PerformanceStatus;
    use App\Enums\PreselectionState;

    $preselection = $competition->preselection;
    $state = $preselection?->state();
    $rules = $preselection?->rules ?? \App\Data\PreselectionRules::defaults();
    $preselection?->loadMissing(['entries.participant.payments', 'entries.scores']);
    $entries = $preselection ? $preselection->entries->sortBy(fn ($e) => [$e->rank ?? PHP_INT_MAX, -$e->likes_count]) : collect();
    $approved = $entries->where('status', PerformanceStatus::Approved);
    $toReview = $entries->whereIn('status', [PerformanceStatus::Pending, PerformanceStatus::Processing]);
    $eligible = $competition->participants->whereIn('status', [\App\Enums\ParticipantStatus::Registered])->count();
    $fmtDate = fn ($d) => $d?->translatedFormat('d M Y, H:i');
    $publicVote = $competition->settings->publicVotingEnabled;
    $weights = $preselection?->effectiveWeights() ?? ['jury' => $rules->juryWeight, 'likes' => $rules->likeWeight];
    $unscored = $preselection ? app(\App\Services\PreselectionService::class)->unscoredCount($preselection) : 0;
    $judgesCount = $competition->judges->where('status', \App\Enums\JudgeStatus::Accepted)->count();
    $filters = [
        'all' => ['Toutes', $entries->count()],
        'review' => ['À valider', $toReview->count()],
        'approved' => ['Validées', $approved->count()],
        'rejected' => ['Rejetées', $entries->where('status', PerformanceStatus::Rejected)->count()],
    ];
    $deadlineLabel = match ($state) {
        PreselectionState::Scheduled => 'Ouverture dans',
        PreselectionState::Open => 'Fin des envois dans',
        PreselectionState::Voting => 'Fin du vote du public dans',
        PreselectionState::Deliberation => 'Fin de la délibération dans',
        default => null,
    };
@endphp

@if (! $preselection)
    <div class="grid gap-6 xl:grid-cols-5">
        <x-ui.card title="Organiser une présélection" icon="funnel" class="xl:col-span-3"
            description="Les artistes inscrits envoient une prestation ; le public like (1 like par compétition) et le jury note ; les meilleurs sont retenus.">
            <x-bo.preselection-form :competition="$competition" :organizer="$organizer" :can-update="$canUpdate" />
        </x-ui.card>
        <x-ui.empty icon="funnel" title="Pas de présélection" description="Sans présélection, vous validez les inscriptions à la main dans l'onglet Participants." class="xl:col-span-2" />
    </div>
@else
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-white">Présélection</h2>
                <x-ui.badge :value="$state" />
            </div>
            <x-ui.button variant="secondary" icon="adjustments-horizontal" x-data x-on:click="$dispatch('open-modal', 'preselection-settings')">
                {{ $canUpdate && $state !== PreselectionState::Published ? 'Configurer' : 'Voir la configuration' }}
            </x-ui.button>
        </div>

        <x-ui.timeline :items="$preselection->timeline()" :deadline="$preselection->nextDeadline()" :deadline-label="$deadlineLabel" />

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-ui.stat label="Étape" :value="$state->label()" icon="calendar-days" tone="blue" :hint="'Début '.$fmtDate($preselection->starts_at)" />
            <x-ui.stat label="Prestations" :value="$entries->count().' / '.$eligible" icon="film" :hint="$toReview->count().' à valider'" />
            <x-ui.stat label="Likes" :value="$entries->sum('likes_count')" icon="heart" tone="red" :hint="$weights['likes'].' % du score'" />
            <x-ui.stat label="À retenir" :value="$rules->selectionSize" icon="trophy" tone="green" :hint="'Jury '.$weights['jury'].' % du score'" />
        </div>

        <x-ui.card title="Classement" icon="list-bullet" :padding="false">
            <x-slot:actions>
                @if ($canRun && $state !== PreselectionState::Published)
                    <form method="POST" action="{{ route('organizers.competitions.preselection.rank', [$organizer, $competition]) }}">@csrf<x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path">Recalculer</x-ui.button></form>
                @endif
                @if ($canUpdate && in_array($state, [PreselectionState::Voting, PreselectionState::Deliberation, PreselectionState::Open], true))
                    <x-ui.badge tone="gray" icon="lock-closed" :dot="false">Publication le {{ $fmtDate($preselection->deliberationEndsAt()) }}</x-ui.badge>
                @endif
                @if ($canUpdate && $state === PreselectionState::Closed)
                    <x-ui.confirm :action="route('organizers.competitions.preselection.publish', [$organizer, $competition])" :danger="false" icon="trophy"
                        title="Publier la sélection ?" :message="'Les '.$rules->selectionSize.' meilleurs artistes deviennent les participants de la compétition, les autres sont « non retenus ». Action définitive.'.($unscored ? ' Attention : '.$unscored.' prestation(s) sans note du jury (comptées 0 pour le jury).' : '')" confirm="Publier">
                        <x-ui.button size="sm" icon="trophy">Publier la sélection</x-ui.button>
                    </x-ui.confirm>
                @endif
            </x-slot:actions>
            <div x-data="{ filter: 'all', q: '' }">
                <div class="space-y-4 px-5 pt-5">
                @if ($toReview->isNotEmpty())
                    <div class="mb-4 flex items-start gap-2 rounded-xl bg-sky-50 p-3 text-sm text-sky-800 ring-1 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200">
                        <x-ui.icon name="information-circle" variant="m" class="mt-0.5 size-4 shrink-0" />
                        <span><strong>{{ $toReview->count() }} prestation(s) à valider.</strong> Regardez le média puis cliquez sur « Valider » : la prestation devient visible du public (likes) et du jury (notes). « Rejeter » avec un motif permet à l'artiste d'en renvoyer une autre.</span>
                    </div>
                @endif
                @if ($unscored > 0 && in_array($state, [PreselectionState::Deliberation, PreselectionState::Closed], true))
                    <div class="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
                        <x-ui.icon name="exclamation-triangle" variant="m" class="mt-0.5 size-4 shrink-0" />
                        {{ $unscored }} prestation(s) validée(s) sans note du jury. {{ $state === PreselectionState::Closed ? 'Elles comptent 0 pour la part jury.' : 'Relancez vos jurés avant la fin de la délibération.' }}
                    </div>
                @endif
                    @if ($entries->isNotEmpty())
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 [scrollbar-width:none]" aria-label="Filtrer les prestations">
                                @foreach ($filters as $key => [$label, $count])
                                    <button type="button" x-on:click="filter = @js($key)"
                                        :class="filter === @js($key) ? 'bg-slate-900 text-white ring-slate-900 dark:bg-white dark:text-slate-900 dark:ring-white' : 'bg-white text-slate-600 ring-slate-200 hover:text-slate-900 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10'"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition">
                                        {{ $label }} <span class="rounded-full bg-slate-500/10 px-1.5 text-xs tabular-nums">{{ $count }}</span>
                                    </button>
                                @endforeach
                            </nav>
                            <div class="relative w-full sm:w-64">
                                <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" x-model="q" placeholder="Rechercher un artiste…" aria-label="Rechercher un artiste"
                                    class="w-full rounded-xl border-0 bg-white py-2 pr-3 pl-9 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                            </div>
                        </div>
                    @endif
                </div>

                @if ($entries->isEmpty())
                    <div class="p-5">
                        <x-ui.empty icon="film" title="Aucune prestation" :description="$state === PreselectionState::Scheduled ? 'Les envois ouvriront le '.$fmtDate($preselection->starts_at).'.' : 'Les artistes inscrits peuvent envoyer leur prestation depuis leur espace.'" />
                    </div>
                @else
                    <div class="mt-4 hidden grid-cols-[2.5rem_minmax(0,1.6fr)_minmax(0,1.5fr)_4.5rem_4.5rem_4.5rem_auto] gap-4 border-y border-slate-100 bg-slate-50/60 px-5 py-2.5 text-xs font-semibold tracking-wide text-slate-400 uppercase lg:grid dark:border-white/5 dark:bg-white/[0.02]">
                        <span>#</span><span>Artiste</span><span>Provenance</span><span class="text-center">Likes</span><span class="text-center">Jury</span><span class="text-center">Score</span><span class="text-right">Actions</span>
                    </div>
                    <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100 lg:mt-0 lg:border-t-0 dark:divide-white/5 dark:border-white/5">
                        @foreach ($entries as $entry)
                            <x-bo.preselection-entry :entry="$entry" :competition="$competition" :organizer="$organizer" :rules="$rules" :can-run="$canRun" :judges-count="$judgesCount" />
                        @endforeach
                    </ul>
                    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-white/5">Score = jury × {{ $weights['jury'] }} % + likes × {{ $weights['likes'] }} % (likes rapportés à la prestation la plus likée). La ligne verte marque la limite des {{ $rules->selectionSize }} artistes retenus. « Voir » ouvre le lecteur, la provenance du fichier et la validation.</p>
                @endif
            </div>
        </x-ui.card>
    </div>

    @push('modals')
        <x-ui.slide-over name="preselection-settings" title="Configuration de la présélection" icon="adjustments-horizontal"
            description="Dates, pondérations et règles des médias. Les règles sont figées dès le début ; les dates restent modifiables.">
            <x-bo.preselection-form :competition="$competition" :organizer="$organizer" :can-update="$canUpdate" />
        </x-ui.slide-over>
    @endpush
@endif
