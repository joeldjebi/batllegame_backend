<x-layouts.portal :title="$competition->name">
    <x-ui.page-header :title="$competition->name" :breadcrumbs="['Compétitions' => route('fan.dashboard'), $competition->name => null]">
        <x-slot:description>
            <x-ui.badge :value="$competition->status" />
            <span>{{ $competition->organizer->name }}</span>
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-4" />{{ $competition->mode->label() }}</span>
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

    @if ($preselection && $preselection->state() !== \App\Enums\PreselectionState::Scheduled)
        @php($pstate = $preselection->state())
        @php($showLikes = $pstate === \App\Enums\PreselectionState::Published || $competition->settings->showLiveResults)
        @php($canLike = $preselection->acceptsLikes())
        <section class="mb-10">
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
                @if ($myLike && $canLike)
                    <form method="POST" action="{{ route('fan.competitions.preselection.unlike', $competition) }}">@csrf @method('DELETE')<x-ui.button type="submit" size="sm" variant="secondary" icon="x-mark">Retirer mon like</x-ui.button></form>
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
                        @php($liked = $myLike === $entry->id)
                        <x-ui.card :padding="false" :class="$liked ? 'ring-2 ring-fuchsia-400' : ''">
                            <div class="p-4">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <p class="truncate font-display font-semibold">{{ $entry->participant->stage_name }}</p>
                                    @if ($pstate === \App\Enums\PreselectionState::Published)
                                        @if ($entry->selected)<x-ui.badge tone="green" icon="trophy" :dot="false">Retenu · {{ $entry->rank }}e</x-ui.badge>@else<x-ui.badge tone="gray" :dot="false">{{ $entry->rank }}e</x-ui.badge>@endif
                                    @endif
                                </div>
                                <x-bo.media-player :performance="$entry" />
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/5">
                                <span class="inline-flex items-center gap-1.5 text-sm text-slate-500"><x-ui.icon name="heart" variant="s" @class(['size-4', 'text-fuchsia-500' => $liked, 'text-slate-300' => ! $liked]) />{{ $showLikes ? $entry->likes_count.' like(s)' : ($liked ? 'Votre like' : '') }}</span>
                                @if ($canLike)
                                    @if (! $user)
                                        <x-ui.button size="sm" variant="secondary" :href="route('fan.login')">Se connecter</x-ui.button>
                                    @elseif (! $liked)
                                        <form method="POST" action="{{ route('fan.competitions.preselection.like', [$competition, $entry]) }}">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" :variant="$myLike ? 'secondary' : 'primary'" icon="heart">{{ $myLike ? 'Déplacer mon like' : 'J\'aime' }}</x-ui.button>
                                        </form>
                                    @else
                                        <x-ui.badge tone="fuchsia" icon="heart" :dot="false">Votre like</x-ui.badge>
                                    @endif
                                @endif
                            </div>
                        </x-ui.card>
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
                @php($media = $match->publishedPerformances()->groupBy('participant_id'))
                @php($voted = $myVotes->get($match->id))
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 dark:border-white/5">
                        <p class="text-sm font-semibold">{{ $match->stage?->name ?? 'Battle' }}</p>
                        <p class="text-xs text-slate-500">
                            @if ($match->voting_closes_at)Fin du vote {{ $match->voting_closes_at->diffForHumans() }}@else Vote ouvert @endif
                        </p>
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

    @if ($results->isNotEmpty())
        <h2 class="mb-4 font-display text-lg font-semibold">Derniers résultats</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($results as $match)
                <x-ui.card :padding="false">
                    <p class="border-b border-slate-100 px-4 py-2 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:border-white/5">{{ $match->stage?->name ?? 'Battle' }}</p>
                    @foreach ($match->slots->filter->participant as $slot)
                        @php($won = $slot->participant_id === $match->winner_id)
                        <div @class(['flex items-center justify-between px-4 py-2.5 text-sm', 'bg-emerald-50/70 font-semibold dark:bg-emerald-500/10' => $won])>
                            <span>@if ($won)🏆 @endif{{ $slot->participant->stage_name }}</span>
                            <span class="font-display tabular-nums">{{ $slot->final_score !== null ? number_format($slot->final_score, 1, ',', '') : '—' }}</span>
                        </div>
                    @endforeach
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-realtime :channels="[\App\Realtime\Channel::competition($competition->id), $user ? \App\Realtime\Channel::user($user->id) : null]" />
</x-layouts.portal>
