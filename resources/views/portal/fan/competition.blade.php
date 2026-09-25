<x-layouts.portal :title="$competition->name">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Compétitions' => route('fan.dashboard'), $competition->name => null]">
        <x-slot:description>
            <x-ui.badge :value="$competition->status" />
            <span>{{ $competition->organizer->name }}</span>
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-4" />{{ $competition->mode->label() }}</span>
            @if ($competition->locationLabel())<span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" variant="m" class="size-4" />{{ $competition->locationLabel() }}</span>@endif
        </x-slot:description>
    </x-ui.page-header>

    @if (! $user)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-50 p-4 text-sm text-brand-800 ring-1 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-200">
            <span>Connectez-vous pour voter.</span>
            <div class="flex gap-2"><x-ui.button size="sm" variant="secondary" :href="route('fan.login')">Connexion</x-ui.button><x-ui.button size="sm" :href="route('fan.register')">Créer un compte</x-ui.button></div>
        </div>
    @elseif (! $user->hasVerifiedPhone())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
            <span>Vérifiez votre numéro de téléphone pour pouvoir voter.</span>
            <x-ui.button size="sm" :href="route('fan.verification.show')" icon="check-badge">Vérifier</x-ui.button>
        </div>
    @endif

    <x-portal.competition-about :competition="$competition" />
    <x-portal.competition-guide :competition="$competition" />

    @if ($preselection)
        @php $pstate = $preselection->state(); @endphp
        @php $canLike = $likes['can_like']; @endphp
        @php $myLike = $likes['my_like']; @endphp
        <section class="mb-10" x-data="preselectionLikes({
            likeUrl: '{{ route('fan.competitions.preselection.like', [$competition, '__ENTRY__']) }}',
            unlikeUrl: '{{ route('fan.competitions.preselection.unlike', $competition) }}',
            loginUrl: '{{ route('fan.login') }}',
            loggedIn: {{ $user ? 'true' : 'false' }},
        })">
            {{-- Server state, re-read by the component after a live refresh. --}}
            <script type="application/json" x-ref="state">@json($likes)</script>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 font-display text-lg font-semibold"><x-ui.icon name="funnel" class="size-5 text-brand-600" /> Présélection <x-ui.badge :value="$pstate" /></h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($canLike)
                            Likez votre prestation préférée : <strong>un seul like par compétition</strong>, modifiable jusqu'au {{ $preselection->voteEndsAt()->translatedFormat('d F à H:i') }}.
                        @elseif (! $preselection->publicVotingEnabled() && $pstate !== \App\Enums\PreselectionState::Published)
                            Sélection 100 % jury : découvrez les prestations des artistes.
                        @elseif ($pstate === \App\Enums\PreselectionState::Deliberation)
                            Le vote est clos : le jury délibère. Résultats après le {{ $preselection->deliberationEndsAt()->translatedFormat('d F à H:i') }}.
                        @elseif ($pstate === \App\Enums\PreselectionState::Closed)
                            Le vote est clos : résultats bientôt.
                        @else
                            Les {{ $preselection->rules->selectionSize }} artistes retenus participent à la compétition.
                        @endif
                    </p>
                </div>
                @if ($user && $canLike)
                    <x-ui.button size="sm" variant="secondary" icon="x-mark" x-show="myLike !== null" x-cloak x-on:click="toggle(myLike)" x-bind:disabled="busy">Retirer mon like</x-ui.button>
                @endif
            </div>

            @if ($canLike)
                <div class="mb-4 flex items-center gap-2 rounded-2xl bg-fuchsia-50 px-4 py-3 text-sm text-fuchsia-800 ring-1 ring-fuchsia-600/20 dark:bg-fuchsia-500/10 dark:text-fuchsia-200" x-data="countdown('{{ $preselection->voteEndsAt()->toIso8601String() }}')">
                    <x-ui.icon name="heart" variant="s" class="size-4 shrink-0" /> Encore <strong class="font-display tabular-nums" x-text="label">{{ $preselection->voteEndsAt()->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</strong> pour liker
                </div>
            @endif

            @if ($entries->isEmpty())
                <x-ui.empty icon="film" title="Aucune prestation publiée pour l'instant" />
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($entries as $entry)
                        <x-portal.entry-card :entry="$entry" :competition="$competition" :likes="$likes" :user="$user" :published="$pstate === \App\Enums\PreselectionState::Published" />
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <h2 class="mb-4 flex items-center gap-2 font-display text-lg font-semibold"><span class="size-2 rounded-full bg-fuchsia-500"></span> Votes en cours</h2>
    @if ($voting->isEmpty())
        <x-ui.empty icon="clock" title="Aucun vote en cours" description="Revenez quand l'organisateur ouvrira le prochain vote." class="mb-10" />
    @else
        <div class="mb-10 space-y-6">
            @foreach ($voting as $match)
                @if ($match->isGroupMatch())
                    @php
                        $media = $match->publishedPerformances()->groupBy('participant_id');
                        $phaseVote = $phaseVotes->get($match->phase_id);
                        $artists = $match->slots->filter(fn ($slot) => $slot->participant && ! $slot->is_forfeit);
                    @endphp
                    <x-ui.card :padding="false" id="match-{{ $match->id }}" class="scroll-mt-24">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 dark:border-white/5">
                            <div>
                                <p class="text-sm font-semibold">{{ $match->group?->name }} · {{ $artists->count() }} artistes</p>
                                <p class="text-xs text-slate-500">
                                    Un seul vote pour toute la phase, toutes poules confondues.
                                    @if ($match->voting_closes_at) Fin du vote {{ $match->voting_closes_at->diffForHumans() }}.@endif
                                </p>
                            </div>
                            <x-portal.share :url="route('fan.competitions.show', $competition).'#match-'.$match->id" :title="($match->group?->name ?? 'Poule').' · '.$competition->name"
                                :text="'Vote pour ton artiste dans « '.$competition->name.' »'" label="Partager la poule" />
                        </div>
                        <form method="POST" action="{{ route('fan.competitions.matches.votes.store', [$competition, $match]) }}">
                            @csrf
                            <div class="grid gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-3 dark:bg-white/5">
                                @foreach ($artists as $slot)
                                    <div class="flex flex-col bg-white p-4 dark:bg-slate-900">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <p class="truncate font-display text-base font-bold">{{ $slot->participant->stage_name }}</p>
                                            @if ($phaseVote === $slot->participant_id)<x-ui.badge tone="green" icon="check">Votre vote</x-ui.badge>@endif
                                        </div>
                                        @forelse ($media->get($slot->participant_id, collect()) as $performance)
                                            <x-bo.media-player :performance="$performance" />
                                        @empty
                                            <div class="grid aspect-video place-items-center rounded-lg bg-slate-50 text-sm text-slate-400 dark:bg-white/5">
                                                {{ $match->phase->effectiveMode() === \App\Enums\CompetitionMode::OnSite ? 'Prestation en direct sur scène' : 'Média à venir' }}
                                            </div>
                                        @endforelse
                                        @if ($user && $phaseVote === null)
                                            <x-ui.button type="submit" name="participant_id" :value="$slot->participant_id" class="mt-auto w-full !mt-4" icon="hand-thumb-up">Voter pour {{ $slot->participant->stage_name }}</x-ui.button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($user && $phaseVote === null && $match->vote_code)
                                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 bg-fuchsia-50 px-5 py-3 dark:border-white/5 dark:bg-fuchsia-500/10">
                                    <x-ui.icon name="map-pin" class="size-5 text-fuchsia-600" />
                                    <label class="text-sm font-medium text-fuchsia-800 dark:text-fuchsia-200">Code affiché dans la salle</label>
                                    <input name="vote_code" inputmode="numeric" maxlength="4" required placeholder="0000" class="w-24 rounded-lg border-0 text-center font-display text-lg font-bold tracking-[0.3em] ring-1 ring-fuchsia-300 focus:ring-2 focus:ring-fuchsia-500 dark:bg-white/10">
                                </div>
                            @endif
                        </form>
                    </x-ui.card>
                    @continue
                @endif
                @php $media = $match->publishedPerformances()->groupBy('participant_id'); @endphp
                @php $voted = $myVotes->get($match->id); @endphp
                @php $names = $match->slots->map(fn ($slot) => $slot->participant?->stage_name)->filter()->implode(' vs '); @endphp
                <x-ui.card :padding="false" id="match-{{ $match->id }}" class="scroll-mt-24">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 dark:border-white/5">
                        <div>
                            <p class="text-sm font-semibold">{{ $match->stage?->name ?? 'Battle' }}</p>
                            <p class="text-xs text-slate-500">
                                @if ($match->voting_closes_at)Fin du vote {{ $match->voting_closes_at->diffForHumans() }}@else Vote ouvert @endif
                            </p>
                        </div>
                        <x-portal.share :url="route('fan.competitions.show', $competition).'#match-'.$match->id" :title="$names.' · '.$competition->name"
                            :text="'Vote pour ton artiste : '.$names.' dans « '.$competition->name.' »'" label="Partager le match" />
                    </div>
                    <form method="POST" action="{{ route('fan.competitions.matches.votes.store', [$competition, $match]) }}" x-data="{ choice: null }">
                        @csrf
                        <div class="grid gap-px bg-slate-100 sm:grid-cols-2 dark:bg-white/5">
                            @foreach ($match->slots->filter->participant as $slot)
                                <div class="bg-white p-5 dark:bg-slate-900">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="font-display text-lg font-bold">{{ $slot->participant->stage_name }}</p>
                                        @if ($voted === $slot->participant_id)<x-ui.badge tone="green" icon="check">Votre vote</x-ui.badge>@endif
                                    </div>
                                    @forelse ($media->get($slot->participant_id, collect()) as $performance)
                                        <x-bo.media-player :performance="$performance" />
                                    @empty
                                        <div class="grid aspect-video place-items-center rounded-lg bg-slate-50 text-sm text-slate-400 dark:bg-white/5">
                                            {{ $match->phase->effectiveMode() === \App\Enums\CompetitionMode::OnSite ? 'Prestation en direct sur scène' : 'Média à venir' }}
                                        </div>
                                    @endforelse
                                    @if ($user && ! $voted)
                                        <x-ui.button type="submit" name="participant_id" :value="$slot->participant_id" class="mt-4 w-full" icon="hand-thumb-up">Voter pour {{ $slot->participant->stage_name }}</x-ui.button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($user && ! $voted && $match->vote_code)
                            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 bg-fuchsia-50 px-5 py-3 dark:border-white/5 dark:bg-fuchsia-500/10">
                                <x-ui.icon name="map-pin" class="size-5 text-fuchsia-600" />
                                <label class="text-sm font-medium text-fuchsia-800 dark:text-fuchsia-200">Code affiché dans la salle</label>
                                <input name="vote_code" inputmode="numeric" maxlength="4" required placeholder="0000" class="w-24 rounded-lg border-0 text-center font-display text-lg font-bold tracking-[0.3em] ring-1 ring-fuchsia-300 focus:ring-2 focus:ring-fuchsia-500 dark:bg-white/10">
                            </div>
                        @endif
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @foreach ($groupResults as $phaseMatches)
        @php
            $resultPhase = $phaseMatches->first()->phase;
        @endphp
        <h2 class="mb-1 font-display text-lg font-semibold">Résultats des poules · Phase {{ $resultPhase->position }}</h2>
        <p class="mb-4 text-sm text-slate-500">Les {{ $resultPhase->qualifiers_per_group }} premier(s) de chaque poule sont qualifiés.</p>
        <div class="mb-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($phaseMatches as $match)
                <x-ui.card :padding="false">
                    <p class="border-b border-slate-100 px-4 py-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:border-white/5">{{ $match->group?->name }}</p>
                    @foreach ($match->slots->filter->participant->sortBy(fn ($slot) => [$slot->is_forfeit ? 1 : 0, $slot->rank ?? PHP_INT_MAX]) as $slot)
                        @php
                            $qualified = ! $slot->is_forfeit && $slot->rank !== null && $slot->rank <= $resultPhase->qualifiers_per_group;
                        @endphp
                        <div @class(['flex items-center gap-3 px-4 py-2.5 text-sm', 'bg-emerald-50/70 font-semibold dark:bg-emerald-500/10' => $qualified])>
                            <span class="w-5 text-xs text-slate-400 tabular-nums">{{ $slot->rank ?? '–' }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $slot->participant->stage_name }}</span>
                            @if ($qualified)<x-ui.badge tone="green" :dot="false">Qualifié</x-ui.badge>@elseif ($slot->is_forfeit)<span class="text-xs text-slate-400">Forfait</span>@endif
                            <span class="w-10 text-right font-display tabular-nums">{{ $slot->final_score !== null ? number_format($slot->final_score, 1, ',', '') : '—' }}</span>
                        </div>
                    @endforeach
                </x-ui.card>
            @endforeach
        </div>
    @endforeach

    @if ($results->isNotEmpty())
        <h2 class="mb-4 font-display text-lg font-semibold">Derniers résultats</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($results as $match)
                <x-ui.card :padding="false">
                    <p class="border-b border-slate-100 px-4 py-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:border-white/5">{{ $match->stage?->name ?? 'Battle' }}</p>
                    @foreach ($match->slots->filter->participant as $slot)
                        @php $won = $slot->participant_id === $match->winner_id; @endphp
                        <div @class(['flex items-center justify-between px-4 py-2.5 text-sm', 'bg-emerald-50/70 font-semibold dark:bg-emerald-500/10' => $won])>
                            <span>{{ $slot->participant->stage_name }}</span>
                            <span class="font-display tabular-nums">{{ $slot->final_score !== null ? number_format($slot->final_score, 1, ',', '') : '—' }}</span>
                        </div>
                    @endforeach
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::competition($competition->id), $user ? \App\Realtime\Channel::user($user->id) : null]" />
</x-layouts.portal>
