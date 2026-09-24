@props(['competition', 'organizer', 'canUpdate' => false, 'canRun' => false])

@php
    use App\Enums\PerformanceStatus;
    use App\Enums\PreselectionState;

    $preselection = $competition->preselection;
    $state = $preselection?->state();
    $rules = $preselection?->rules ?? \App\Data\PreselectionRules::defaults();
    // Thousands of entries: counts by query, one page of 20 rows (filter / search / page in the URL).
    $counts = $preselection ? $preselection->entries()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();
    $countOf = fn (PerformanceStatus ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s->value] ?? 0);
    $allCount = (int) $counts->sum();
    $reviewCount = $countOf(PerformanceStatus::Pending, PerformanceStatus::Processing);
    $filterKey = in_array(request('pre_filter'), ['review', 'approved', 'rejected'], true) ? request('pre_filter') : 'all';
    $search = trim((string) request('pre_q'));
    $entries = $preselection
        ? $preselection->entries()
            ->with(['participant.payments', 'participant.user', 'participant.competition', 'scores'])
            ->when($filterKey === 'review', fn ($q) => $q->whereIn('status', [PerformanceStatus::Pending, PerformanceStatus::Processing]))
            ->when($filterKey === 'approved', fn ($q) => $q->where('status', PerformanceStatus::Approved))
            ->when($filterKey === 'rejected', fn ($q) => $q->where('status', PerformanceStatus::Rejected))
            ->when($search !== '', fn ($q) => $q->whereHas('participant', fn ($p) => $p->whereLike('stage_name', "%{$search}%")))
            ->orderByRaw('rank is null')->orderBy('rank')->orderByDesc('likes_count')->orderBy('id')
            ->paginate(20, ['*'], 'pre_page')->withQueryString()->fragment('preselection')
        : null;
    $entries?->getCollection()->each->setRelation('preselection', $preselection);
    $eligible = $competition->participants->filter->canEnterPreselection()->count();
    $fmtDate = fn ($d) => $d?->translatedFormat('d M Y, H:i');
    $publicVote = $competition->settings->publicVotingEnabled;
    $weights = $preselection?->effectiveWeights() ?? ['jury' => $rules->juryWeight, 'likes' => $rules->likeWeight];
    $unscored = $preselection ? app(\App\Services\PreselectionService::class)->unscoredCount($preselection) : 0;
    $judgesCount = $preselection?->splitsJudging() ? $preselection->judges_per_entry : $competition->judges->where('status', \App\Enums\JudgeStatus::Accepted)->count();
    $progress = $preselection ? app(\App\Services\JuryWorkload::class)->progress($preselection) : [];
    $filters = [
        'all' => ['Toutes', $allCount],
        'review' => ['À valider', $reviewCount],
        'approved' => ['Validées', $countOf(PerformanceStatus::Approved)],
        'rejected' => ['Rejetées', $countOf(PerformanceStatus::Rejected)],
    ];
    $filterUrl = fn (string $key) => request()->fullUrlWithQuery(['pre_filter' => $key === 'all' ? null : $key, 'pre_page' => null]).'#preselection';
    $deadlineLabel = match ($state) {
        PreselectionState::Open => 'Date limite d\'envoi dans',
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
            <x-ui.stat label="Étape" :value="$state->label()" icon="calendar-days" tone="blue" :hint="'Envois jusqu\'au '.$fmtDate($preselection->ends_at)" />
            <x-ui.stat label="Prestations" :value="$allCount.' / '.$eligible" icon="film" :hint="$reviewCount.' à valider'" />
            <x-ui.stat label="Likes" :value="(int) $preselection->entries()->sum('likes_count')" icon="heart" tone="red" :hint="$weights['likes'].' % du score'" />
            <x-ui.stat label="À retenir" :value="$rules->selectionSize" icon="trophy" tone="green" :hint="'Jury '.$weights['jury'].' % du score'" />
        </div>

        @if ($progress !== [])
            <x-ui.card title="Avancement du jury" icon="scale" :description="$preselection->splitsJudging() ? 'Prestations réparties : '.$preselection->judges_per_entry.' juré(s) par prestation.' : 'Chaque juré note toutes les prestations validées.'">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($progress as ['judge' => $judge, 'scored' => $done, 'total' => $of])
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                            <div class="flex items-center gap-2">
                                <x-ui.avatar :name="$judge->user->name" :src="$judge->user->avatarUrl()" size="sm" />
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $judge->user->name }}</span>
                                <span @class(['text-sm font-bold tabular-nums', 'text-emerald-600' => $of && $done === $of])>{{ $done }} / {{ $of }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $of ? round($done / $of * 100) : 0 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

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
            <div>
                <div class="space-y-4 px-5 pt-5">
                @if ($reviewCount)
                    <div class="mb-4 flex items-start gap-2 rounded-xl bg-sky-50 p-3 text-sm text-sky-800 ring-1 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200">
                        <x-ui.icon name="information-circle" variant="m" class="mt-0.5 size-4 shrink-0" />
                        <span><strong>{{ $reviewCount }} prestation(s) à valider.</strong> Regardez le média puis cliquez sur « Valider » : la prestation devient visible du public (likes) et du jury (notes). « Rejeter » avec un motif permet à l'artiste d'en renvoyer une autre.</span>
                    </div>
                @endif
                @if ($unscored > 0 && in_array($state, [PreselectionState::Deliberation, PreselectionState::Closed], true))
                    <div class="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
                        <x-ui.icon name="exclamation-triangle" variant="m" class="mt-0.5 size-4 shrink-0" />
                        {{ $unscored }} prestation(s) validée(s) sans note du jury. {{ $state === PreselectionState::Closed ? 'Elles comptent 0 pour la part jury.' : 'Relancez vos jurés avant la fin de la délibération.' }}
                    </div>
                @endif
                    @if ($allCount)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 [scrollbar-width:none]" aria-label="Filtrer les prestations">
                                @foreach ($filters as $key => [$label, $count])
                                    <a href="{{ $filterUrl($key) }}" @class([
                                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition',
                                        'bg-slate-900 text-white ring-slate-900 dark:bg-white dark:text-slate-900 dark:ring-white' => $filterKey === $key,
                                        'bg-white text-slate-600 ring-slate-200 hover:text-slate-900 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' => $filterKey !== $key,
                                    ])>{{ $label }} <span class="rounded-full bg-slate-500/10 px-1.5 text-xs tabular-nums">{{ $count }}</span></a>
                                @endforeach
                            </nav>
                            <form method="GET" action="{{ request()->url() }}#preselection" class="relative w-full sm:w-64">
                                @if ($filterKey !== 'all')<input type="hidden" name="pre_filter" value="{{ $filterKey }}">@endif
                                <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" name="pre_q" value="{{ $search }}" placeholder="Rechercher un artiste…" aria-label="Rechercher un artiste"
                                    class="w-full rounded-xl border-0 bg-white py-2 pr-3 pl-9 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                            </form>
                        </div>
                    @endif
                </div>

                @if ($entries->isEmpty())
                    <div class="p-5">
                        <x-ui.empty icon="film" :title="$allCount ? 'Aucune prestation ne correspond' : 'Aucune prestation'" :description="$allCount ? 'Changez de filtre ou de recherche.' : 'Les artistes dont l\'inscription est validée peuvent envoyer leur prestation depuis leur espace, jusqu\'au '.$fmtDate($preselection->ends_at).'.'" />
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
                    @if ($entries->hasPages())<div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $entries->links() }}</div>@endif
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
