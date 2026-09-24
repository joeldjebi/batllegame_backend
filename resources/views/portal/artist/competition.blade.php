@php
    use App\Enums\PhaseType;
    use App\Enums\PreselectionState;
    use App\Services\ArtistJourney;

    $user = $participant->user;
    $fmt = fn ($date) => $date?->translatedFormat('d M · H:i');
    $entry = $participant->preselectionEntry;
    $preselection = $competition->preselection;
    $dot = [
        'won' => 'bg-emerald-500 text-white', 'done' => 'bg-emerald-500 text-white',
        'current' => 'bg-brand-600 text-white ring-4 ring-brand-100 dark:ring-brand-500/20', 'waiting' => 'bg-amber-500 text-white',
        'lost' => 'bg-rose-500 text-white', 'upcoming' => 'bg-slate-200 text-slate-500 dark:bg-white/10', 'skipped' => 'bg-slate-100 text-slate-300 dark:bg-white/5',
    ];
    $badge = ['won' => 'green', 'done' => 'green', 'current' => 'violet', 'waiting' => 'amber', 'lost' => 'red', 'upcoming' => 'gray', 'skipped' => 'gray'];
@endphp

<x-layouts.portal :title="$competition->name">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Mon espace' => route('artist.dashboard'), $competition->name => null]">
        <x-slot:description>
            <x-ui.badge :value="$participant->status" />
            <span>{{ $competition->organizer->name }}</span>
            <a href="{{ route('fan.competitions.show', $competition) }}" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Page publique</a>
            @if (filled($competition->regulations) || $competition->scheduleList() !== [])
                <a href="{{ route('fan.competitions.show', $competition) }}#reglement" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">Déroulé & règlement</a>
            @endif
        </x-slot:description>
    </x-ui.page-header>

    {{-- Me + the one next thing to do --}}
    <section @class([
        'mb-8 overflow-hidden rounded-3xl p-5 text-white shadow-lift sm:p-7',
        'bg-brand-600' => ! $out && ! $champion,
        'bg-amber-500' => $champion,
        'bg-slate-700' => $out,
    ])>
        <div class="flex items-center gap-4">
            <x-ui.avatar :name="$participant->stage_name" :src="$user->avatarUrl()" size="lg" class="!ring-white/30" />
            <div class="min-w-0">
                <p class="text-sm text-white/70">{{ $participant->stage_name }}</p>
                <p class="font-display text-xl font-extrabold sm:text-2xl">
                    @if ($champion) Vainqueur de la compétition 🏆
                    @elseif ($out) Parcours terminé
                    @elseif ($next) {{ $next['stage'] }}
                    @else En attente de la suite
                    @endif
                </p>
            </div>
        </div>

        @if ($next && ! $out)
            <p class="mt-4 text-white/90">{{ $next['text'] }}</p>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                @if ($next['deadline'])
                    <div x-data="countdown('{{ $next['deadline']->toIso8601String() }}')" class="rounded-2xl bg-white/15 px-4 py-2 ring-1 ring-white/20">
                        <p class="text-[11px] font-semibold tracking-wide text-white/70 uppercase">Temps restant</p>
                        <p class="font-display text-lg font-extrabold tabular-nums" x-text="label">{{ $next['deadline']->diffForHumans() }}</p>
                    </div>
                @endif
                @if (in_array($next['type'], ['submit', 'sent'], true))
                    <a href="#etape-{{ $next['stageModel']->id }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-soft">{{ $next['type'] === 'submit' ? 'Envoyer ma prestation' : 'Voir ma prestation' }}</a>
                @elseif ($next['type'] === 'vote')
                    <x-portal.share :url="route('fan.competitions.show', $competition).'#match-'.$next['match']->id" :title="$participant->stage_name.' · '.$competition->name"
                        :text="'Vote pour moi dans « '.$competition->name.' » 🔥'" label="Partager pour avoir des votes" variant="secondary" align="left" />
                @endif
            </div>
        @elseif ($out)
            <p class="mt-4 text-white/85">Merci pour ta participation ! Tes résultats restent visibles ci-dessous.</p>
        @elseif (! $champion)
            <p class="mt-4 text-white/85">Rien à faire pour le moment : tu seras prévenu dès que la prochaine étape commence.</p>
        @endif
    </section>

    {{-- The journey --}}
    <ol class="space-y-6">
        @if ($preselection)
            @php
                $pstate = $preselection->state();
                $selected = $pstate === PreselectionState::Published;
                $preState = ! $selected ? 'current' : ($entry?->selected ? 'won' : 'lost');
            @endphp
            <li class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-slate-900/60 dark:ring-white/10">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $dot[$preState] }}"><x-ui.icon name="funnel" variant="m" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-lg font-bold">Présélection</h2>
                        <p class="text-xs text-slate-500">Envois jusqu'au {{ $fmt($preselection->ends_at) }} · {{ $preselection->rules->selectionSize }} artistes retenus</p>
                    </div>
                    <x-ui.badge :tone="$badge[$preState]" :dot="false">
                        {{ ! $selected ? $pstate->label() : ($entry?->selected ? 'Sélectionné'.($entry->rank ? ' · '.$entry->rank.'e' : '') : 'Non retenu') }}
                    </x-ui.badge>
                </div>
            </li>
        @endif

        @foreach ($phases as ['phase' => $phase, 'stages' => $stages, 'state' => $phaseState])
            <li class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-slate-900/60 dark:ring-white/10">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $dot[$phaseState] }}"><x-ui.icon :name="$phase->type->icon()" variant="m" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-lg font-bold">Phase {{ $phase->position }} · {{ $phase->type === PhaseType::Groups ? 'Poules' : $phase->type->label() }}</h2>
                        <p class="text-xs text-slate-500">
                            {{ ArtistJourney::isOnline($phase) ? 'En ligne : vidéo à envoyer à chaque étape' : 'Sur scène' }}
                            @if ($phase->type === PhaseType::Groups) · {{ $phase->qualifiers_per_group }} qualifié(s) par poule @else · battles à 1 contre 1 @endif
                            · {{ $phase->rules->voteMode->label() }}
                        </p>
                    </div>
                </div>

                <ol class="mt-5 space-y-3 border-l-2 border-slate-100 pl-5 dark:border-white/10">
                    @foreach ($stages as $row)
                        @php
                            $match = $row['match'];
                            $others = $match ? ArtistJourney::others($match, $participant) : collect();
                            $stageModel = $row['stage'];
                        @endphp
                        <li @if ($stageModel) id="etape-{{ $stageModel->id }}" @endif class="relative scroll-mt-24">
                            <span class="absolute top-2 -left-[1.72rem] size-3 rounded-full {{ $dot[$row['state']] }}"></span>
                            <div @class(['rounded-2xl p-4 ring-1', 'bg-brand-50/60 ring-brand-200 dark:bg-brand-500/5 dark:ring-brand-500/20' => $row['state'] === 'current', 'ring-slate-100 dark:ring-white/10' => $row['state'] !== 'current', 'opacity-60' => $row['state'] === 'skipped'])>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold">{{ $row['name'] }}</p>
                                    <x-ui.badge :tone="$badge[$row['state']]" :dot="false">{{ $row['label'] }}</x-ui.badge>
                                </div>
                                <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500">
                                    @if ($row['dates']['submission'])<span>Envoi avant le {{ $fmt($row['dates']['submission']) }}</span>@endif
                                    @if ($row['dates']['voting_closes'])<span>Vote jusqu'au {{ $fmt($row['dates']['voting_closes']) }}</span>@endif
                                    @if (! $row['dates']['submission'] && ! $row['dates']['voting_closes'])<span>Dates bientôt communiquées</span>@endif
                                </p>

                                {{-- My group or my opponent --}}
                                @if ($match)
                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                        @if ($match->isGroupMatch())
                                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $match->group?->name }} :</span>
                                            <div class="flex -space-x-2">
                                                <x-ui.avatar :name="$participant->stage_name" :src="$user->avatarUrl()" size="sm" class="!ring-brand-500" />
                                                @foreach ($others->take(6) as $other)<x-ui.avatar :name="$other->stage_name" :src="$other->user?->avatarUrl()" size="sm" />@endforeach
                                            </div>
                                            <span class="text-xs text-slate-500">toi + {{ $others->count() }} artiste(s) · chacun présente sa prestation</span>
                                        @elseif ($others->isNotEmpty())
                                            <span class="text-slate-500">Face à</span>
                                            <x-ui.avatar :name="$others[0]->stage_name" :src="$others[0]->user?->avatarUrl()" size="sm" />
                                            <span class="font-semibold">{{ $others[0]->stage_name }}</span>
                                        @else
                                            <span class="text-slate-500">Adversaire à déterminer</span>
                                        @endif
                                        @if ($row['slot']?->final_score !== null && $match->resultsArePublic())
                                            <span class="ml-auto font-display font-bold tabular-nums">{{ number_format($row['slot']->final_score, 1, ',', '') }} / 100</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- My performance for this stage --}}
                                @if ($row['submission']?->media_path)
                                    <div x-data="{ open: false }" class="mt-3">
                                        <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 dark:text-brand-300">
                                            <x-ui.icon name="play-circle" variant="m" class="size-4" /> Ma prestation · <x-ui.badge :value="$row['submission']->status" />
                                        </button>
                                        <div x-show="open" x-collapse x-cloak class="mt-2 overflow-hidden rounded-xl"><x-bo.media-player :performance="$row['submission']" preload="none" /></div>
                                        @if ($row['submission']->rejection_reason)<p class="mt-2 text-xs text-rose-600">Motif : {{ $row['submission']->rejection_reason }}</p>@endif
                                    </div>
                                @endif

                                @if (in_array($row['action']['type'] ?? null, ['submit', 'sent'], true))
                                    @php
                                        $rules = $phase->rules;
                                    @endphp
                                    <form method="POST" enctype="multipart/form-data" class="mt-4" action="{{ route('artist.competitions.stages.submit', [$competition, $stageModel]) }}">
                                        @csrf
                                        <x-portal.dropzone :accept="implode(',', $rules->acceptedMimeTypes())" :max-mb="$rules->mediaMaxSizeMb"
                                            :hint="($row['action']['type'] === 'sent' ? 'Remplacer ma prestation · ' : '').collect($rules->mediaTypes)->map->label()->implode(' ou ').' · '.gmdate('i:s', $rules->mediaMaxDuration).' max · '.$rules->mediaMaxSizeMb.' Mo max'" />
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                    @if ($stages === [])
                        <li class="text-sm text-slate-500">Le programme de cette phase sera communiqué à son démarrage.</li>
                    @endif
                </ol>
            </li>
        @endforeach

        @if ($phases === [])
            <x-ui.empty icon="calendar" title="Programme bientôt disponible" description="L'organisateur prépare les phases de la compétition." />
        @endif
    </ol>
</x-layouts.portal>
