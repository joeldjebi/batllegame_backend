@php
    use App\Enums\ParticipantStatus;
    use App\Enums\PerformanceStatus;
    use App\Enums\PreselectionState;

    $user = auth('member')->user();
    $active = $participations->reject(fn ($j) => $j['out'])->count();
    $fmtFee = fn ($c) => $c->entry_fee ? number_format($c->entry_fee, 0, ',', ' ').' '.$c->currency : 'Gratuit';
@endphp

<x-layouts.portal title="Mon espace">
    {{-- Hero --}}
    <section class="relative -mx-4 -mt-6 overflow-hidden bg-brand-600 px-4 pt-8 pb-8 text-white sm:mx-0 sm:mt-0 sm:rounded-3xl sm:px-8 sm:pt-10 sm:pb-10">
        <div class="absolute -top-20 -right-20 size-64 rounded-full border-[36px] border-white/10"></div>
        <div class="absolute right-24 -bottom-24 size-40 rounded-full bg-brand-700"></div>
        <div class="relative flex items-center gap-4">
            <a href="{{ route('artist.profile.edit') }}" class="relative shrink-0" title="Mon profil">
                <x-ui.avatar :name="$user->name" :src="$user->avatarUrl()" size="lg" class="!ring-white/30" />
                @unless ($user->avatar_path)<span class="absolute -right-1 -bottom-1 grid size-6 place-items-center rounded-full bg-white text-brand-700 shadow"><x-ui.icon name="camera" variant="m" class="size-3.5" /></span>@endunless
            </a>
            <div class="min-w-0">
                <p class="text-sm text-white/70">Espace artiste</p>
                <h1 class="truncate font-display text-2xl font-extrabold sm:text-3xl">Salut, {{ Str::before($user->name, ' ') }}</h1>
            </div>
        </div>
        <dl class="relative mt-7 grid grid-cols-3 gap-2 sm:max-w-lg sm:gap-3">
            @foreach ([['Compétitions', $participations->count()], ['En course', $active], ['À faire', $actions->count()]] as [$label, $value])
                <div class="rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/15 sm:px-4">
                    <dt class="text-[11px] font-medium tracking-wide text-white/70 uppercase">{{ $label }}</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="mt-8 space-y-10">
        @unless ($user->avatar_path)
            <a href="{{ route('artist.profile.edit') }}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 transition hover:ring-brand-300 dark:bg-white/5 dark:ring-white/10">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon name="camera" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">Ajoute ta photo de profil</span>
                    <span class="block text-xs text-slate-500">Elle accompagne tes prestations : l'organisateur, le jury et le public te reconnaissent.</span>
                </span>
                <x-ui.icon name="chevron-right" variant="m" class="size-5 text-slate-300" />
            </a>
        @endunless

        {{-- Action center --}}
        @if ($actions->isNotEmpty())
            <section class="space-y-4">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><span class="grid size-6 place-items-center rounded-full bg-rose-500 text-xs text-white">{{ $actions->count() }}</span> À faire maintenant</h2>
                @foreach ($actions as $journey)
                    @if ($journey['action'] === 'pay')
                        <x-portal.payment-pitch :competition="$journey['competition']" :deadline="$journey['deadline']" />
                    @else
                        @php
                            $isPreselection = $journey['action'] === 'preselection';
                            $mediaRules = $isPreselection ? $journey['preselection']->rules : $journey['stage']->phase->rules;
                        @endphp
                        <div class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-7 dark:bg-slate-900 dark:ring-white/10">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase dark:text-brand-300">{{ $isPreselection ? 'Présélection' : $journey['stage']->name }}</p>
                                    <h3 class="mt-1 font-display text-xl font-bold">{{ $journey['competition']->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $isPreselection ? 'Envoie ta prestation : le public like et le jury note, les '.$mediaRules->selectionSize.' meilleurs sont retenus.' : 'Envoie ta prestation pour cette étape avant la date limite, sinon forfait.' }}
                                    </p>
                                </div>
                                @if ($journey['deadline'])
                                    <div x-data="countdown('{{ $journey['deadline']->toIso8601String() }}')" :class="urgent ? 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200'"
                                        class="w-full rounded-2xl px-4 py-2.5 text-center ring-1 sm:w-auto">
                                        <p class="text-[11px] font-semibold tracking-wide uppercase opacity-80">Temps restant</p>
                                        <p class="font-display text-lg font-extrabold tabular-nums" x-text="label">{{ $journey['deadline']->diffForHumans() }}</p>
                                    </div>
                                @endif
                            </div>

                            @php
                                $current = $isPreselection ? $journey['entry'] : $journey['submission'];
                            @endphp
                            @if ($current?->status === PerformanceStatus::Rejected)
                                <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300"><strong>Prestation refusée :</strong> {{ $current->rejection_reason }}. Envoie une nouvelle version.</p>
                            @endif

                            @if (request()->user()?->avatar_path === null)
                                <x-portal.photo-required class="mt-5" />
                            @else
                                <form method="POST" enctype="multipart/form-data" class="mt-5"
                                    action="{{ $isPreselection ? route('artist.competitions.preselection.submit', $journey['competition']) : route('artist.competitions.stages.submit', [$journey['competition'], $journey['stage']]) }}">
                                    @csrf
                                    <x-portal.dropzone :accept="implode(',', $mediaRules->acceptedMimeTypes())" :max-mb="$mediaRules->mediaMaxSizeMb"
                                        :hint="collect($mediaRules->mediaTypes)->map->label()->implode(' ou ').' · '.gmdate('i:s', $mediaRules->mediaMaxDuration).' max · '.$mediaRules->mediaMaxSizeMb.' Mo max'" />
                                </form>
                            @endif
                        </div>
                    @endif
                @endforeach
            </section>
        @endif

        {{-- My competitions --}}
        <section>
            <h2 class="mb-4 font-display text-lg font-bold">Mes compétitions</h2>
            @if ($participations->isEmpty())
                <x-ui.empty icon="microphone" title="Pas encore de compétition" description="Inscris-toi à une compétition ouverte ci-dessous pour commencer." />
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($participations as $journey)
                        @php
                            $participant = $journey['participant'];
                            $competition = $journey['competition'];
                            $entry = $journey['entry'];
                            $media = $entry ?? $journey['submission'];
                            $preselection = $journey['preselection'];
                            $likesOpen = $entry && $entry->status === PerformanceStatus::Approved && $preselection?->acceptsLikes();
                            $entryUrl = $entry && $entry->status === PerformanceStatus::Approved ? route('fan.competitions.preselection.entry', [$competition, $entry]) : null;
                            $shareUrl = $entryUrl ?? route('fan.competitions.show', $competition);
                            $modal = 'my-media-'.$participant->id;
                            [$tone, $icon, $message] = match (true) {
                                ! $journey['paid'] => ['amber', 'lock-closed', "Envoi verrouillé jusqu'au paiement ({$fmtFee($competition)})."],
                                $journey['state'] === PreselectionState::Published && $participant->status === ParticipantStatus::Validated => ['green', 'trophy', 'Sélectionné'.($entry?->rank ? ' · '.$entry->rank.'e de la présélection' : '').' ! Prépare-toi pour la compétition.'],
                                $journey['state'] === PreselectionState::Published => ['gray', 'face-frown', 'Pas retenu cette fois'.($entry?->rank ? ' ('.$entry->rank.'e)' : '').'. Merci et à la prochaine !'],
                                $participant->awaitsApproval() && $journey['state'] !== PreselectionState::Published => ['amber', 'clock', "Inscription en attente de validation par l'organisateur : tu pourras envoyer ta prestation dès qu'elle sera validée."],
                                $journey['action'] === 'preselection' => ['violet', 'arrow-up-tray', 'Envoie ta prestation avant le '.$preselection->ends_at->translatedFormat('d F à H:i').' (voir « À faire »).'],
                                $likesOpen => ['fuchsia', 'heart', 'Partage ta prestation : le public peut liker jusqu\'au '.$preselection->voteEndsAt()->translatedFormat('d F à H:i').'.'],
                                $journey['state'] === PreselectionState::Deliberation => ['violet', 'scale', 'Le jury délibère : résultats après le '.$preselection->deliberationEndsAt()->translatedFormat('d F à H:i').'.'],
                                $journey['state'] === PreselectionState::Closed => ['gray', 'clock', 'Présélection terminée : résultats bientôt.'],
                                $entry && $entry->status === PerformanceStatus::Pending => ['amber', 'clock', "Prestation reçue : en attente de validation par l'organisateur."],
                                $journey['playing'] && $journey['submission'] => ['violet', 'film', $journey['stage']->name.' : prestation '.mb_strtolower($journey['submission']->status->label()).'.'],
                                $journey['stage'] && ! $journey['stage']->isOnline() && $journey['playing'] => ['violet', 'map-pin', $journey['stage']->name.' en présentiel : rendez-vous sur scène.'],
                                $journey['out'] => ['gray', 'flag', 'Parcours terminé pour cette compétition.'],
                                default => ['gray', 'check-circle', 'Rien à faire pour le moment.'],
                            };
                            $toneClasses = [
                                'amber' => 'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
                                'green' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200',
                                'violet' => 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-200',
                                'fuchsia' => 'bg-fuchsia-50 text-fuchsia-800 dark:bg-fuchsia-500/10 dark:text-fuchsia-200',
                                'gray' => 'bg-slate-50 text-slate-600 dark:bg-white/5 dark:text-slate-300',
                            ][$tone];
                        @endphp
                        <article @class(['flex flex-col rounded-3xl bg-white shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10', 'opacity-80' => $journey['out']])>
                            <header class="flex items-start gap-3 p-4 sm:p-5">
                                <span @class(['grid size-11 shrink-0 place-items-center rounded-2xl text-white', 'bg-brand-600' => ! $journey['out'], 'bg-slate-400 dark:bg-slate-600' => $journey['out']])>
                                    <x-ui.icon :name="$competition->discipline->icon()" class="size-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('artist.competitions.show', $competition) }}" class="line-clamp-2 font-display text-base leading-snug font-bold hover:text-brand-700 sm:text-lg dark:hover:text-brand-300">{{ $competition->name }}</a>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">
                                        {{ $competition->organizer->name }} · {{ $competition->discipline->label() }}
                                        · <a href="{{ route('fan.competitions.show', $competition) }}#reglement" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Déroulé & règlement</a>
                                    </p>
                                </div>
                                <x-ui.badge :value="$participant->status" class="shrink-0" />
                            </header>

                            <x-portal.progress :steps="$journey['steps']" class="px-4 sm:px-5" />

                            <div class="mx-4 mt-4 flex items-start gap-2.5 rounded-2xl p-3 text-sm sm:mx-5 {{ $toneClasses }}">
                                <x-ui.icon :name="$icon" variant="m" class="mt-0.5 size-4 shrink-0" />
                                <p class="min-w-0">{{ $message }}</p>
                            </div>

                            @if ($media?->media_path)
                                <div class="mx-4 mt-3 flex items-center gap-3 rounded-2xl p-2 ring-1 ring-slate-100 sm:mx-5 dark:ring-white/10">
                                    <button type="button" x-data x-on:click="$dispatch('open-modal', '{{ $modal }}')" class="group relative aspect-video w-28 shrink-0 overflow-hidden rounded-xl bg-slate-900" aria-label="Revoir ma prestation">
                                        @if ($media->poster_path)
                                            <img src="{{ $media->posterUrl() }}" alt="" loading="lazy" class="pointer-events-none size-full object-cover opacity-80">
                                        @elseif ($media->media_type === \App\Enums\MediaType::Video)
                                            <video src="{{ $media->mediaUrl() }}#t=0.5" preload="metadata" muted playsinline class="pointer-events-none size-full object-cover opacity-80"></video>
                                        @endif
                                        <span class="absolute inset-0 grid place-items-center"><span class="grid size-9 place-items-center rounded-full bg-white/90 text-slate-900 shadow transition group-active:scale-95"><x-ui.icon name="play" variant="s" class="size-4" /></span></span>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-slate-500">{{ $entry ? 'Ma prestation de présélection' : ($journey['stage']?->name ?? 'Ma prestation') }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <x-ui.badge :value="$media->status" />
                                            @if ($entry)
                                                <span class="inline-flex items-center gap-1 text-sm font-bold text-fuchsia-600 tabular-nums dark:text-fuchsia-300"><x-ui.icon name="heart" variant="s" class="size-4" />{{ $entry->likes_count }}</span>
                                            @endif
                                        </div>
                                        @if ($media->rejection_reason)<p class="mt-1 line-clamp-2 text-xs text-rose-600">{{ $media->rejection_reason }}</p>@endif
                                    </div>
                                </div>
                            @endif

                            <footer class="mt-auto grid grid-cols-2 gap-2 p-4 sm:p-5">
                                @if (! $journey['paid'])
                                    <x-ui.button :href="route('artist.competitions.payment', $competition)" icon="credit-card" class="col-span-2">Débloquer · {{ $fmtFee($competition) }}</x-ui.button>
                                @else
                                    <x-portal.share :url="$shareUrl" :title="$participant->stage_name.' · '.$competition->name"
                                        :text="$likesOpen ? 'Soutiens-moi dans « '.$competition->name.' » : un like peut tout changer.' : 'Suis « '.$competition->name.' » sur Battle Game'"
                                        :label="$likesOpen ? 'Partager' : 'Inviter'" :variant="$likesOpen ? 'primary' : 'secondary'" align="left" class="w-full [&>button]:w-full" />
                                    <x-ui.button variant="secondary" :href="route('artist.competitions.show', $competition)" icon="map" class="w-full">Mon parcours</x-ui.button>
                                @endif
                            </footer>
                        </article>

                        @if ($media?->media_path)
                            @push('modals')
                                <x-ui.modal :name="$modal" :title="$competition->name" icon="film" max-width="2xl" :description="$participant->stage_name.' · '.$media->status->label()">
                                    <x-bo.media-player :performance="$media" preload="none" />
                                </x-ui.modal>
                            @endpush
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Open competitions --}}
        <section>
            <div class="mb-4 flex items-end justify-between gap-3">
                <h2 class="font-display text-lg font-bold">Inscriptions ouvertes</h2>
                @if ($open->count() > 1)<span class="text-xs text-slate-400 sm:hidden">Faites défiler →</span>@endif
            </div>
            @if ($open->isEmpty())
                <x-ui.empty icon="calendar" title="Aucune inscription ouverte" description="De nouvelles compétitions arrivent bientôt." />
            @else
                <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3">
                    @foreach ($open as $competition)
                        <article class="flex w-[85%] shrink-0 snap-center flex-col rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:w-auto dark:bg-slate-900 dark:ring-white/10">
                            <div class="flex items-start justify-between gap-2">
                                <span class="grid size-12 place-items-center rounded-2xl bg-brand-600 text-white"><x-ui.icon :name="$competition->discipline->icon()" class="size-6" /></span>
                                <x-ui.badge tone="gray" :dot="false" :icon="$competition->mode->icon()">{{ $competition->mode->label() }}</x-ui.badge>
                            </div>
                            <h3 class="mt-4 font-display text-lg font-bold">{{ $competition->name }}</h3>
                            <p class="text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }}</p>
                            @if (filled($competition->description))
                                <p class="mt-3 line-clamp-3 text-sm text-slate-600 dark:text-slate-300">{{ \App\Support\RichText::excerpt($competition->description, 220) }}</p>
                            @endif
                            @if ($topPrize = $competition->prizeList()[0] ?? null)
                                <p class="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                    <x-ui.icon name="trophy" variant="s" class="mt-0.5 size-4 shrink-0 text-amber-500" />
                                    <span><span class="font-semibold">{{ $topPrize['rank'] }} :</span> {{ $topPrize['reward'] }}@if (count($competition->prizeList()) > 1)<span class="text-amber-700/70 dark:text-amber-300/70"> · +{{ count($competition->prizeList()) - 1 }} autre(s)</span>@endif</span>
                                </p>
                            @endif
                            <a href="{{ route('fan.competitions.show', $competition) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 dark:text-brand-300">Voir les détails <x-ui.icon name="arrow-right" variant="m" class="size-4" /></a>
                            <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-white/5"><dt class="text-slate-400">Frais</dt><dd class="font-semibold">{{ $fmtFee($competition) }}</dd></div>
                                <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-white/5"><dt class="text-slate-400">Inscrits</dt><dd class="font-semibold">{{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }}</dd></div>
                                @if ($competition->preselection)
                                    <div class="col-span-2 rounded-xl bg-fuchsia-50 px-3 py-2 text-fuchsia-800 dark:bg-fuchsia-500/10 dark:text-fuchsia-200"><dt class="opacity-70">Présélection</dt><dd class="font-semibold">{{ $competition->preselection->rules->selectionSize }} artistes retenus · jusqu'au {{ $competition->preselection->ends_at->translatedFormat('d M') }}</dd></div>
                                @endif
                            </dl>
                            <form method="POST" action="{{ route('artist.competitions.register', $competition) }}" class="mt-5 space-y-2">
                                @csrf
                                <input name="stage_name" required placeholder="Ton nom de scène" value="{{ $user->name }}" class="block w-full rounded-xl border-0 bg-slate-50 py-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                                <x-ui.button type="submit" size="lg" class="w-full" icon="user-plus">S'inscrire{{ $competition->entry_fee ? ' · '.$fmtFee($competition) : '' }}</x-ui.button>
                                @if (filled($competition->regulations))
                                    <p class="text-center text-xs text-slate-500">En t'inscrivant, tu acceptes le <a href="{{ route('fan.competitions.show', $competition) }}#reglement" class="font-semibold text-brand-700 underline dark:text-brand-300">règlement</a>.</p>
                                @endif
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::user($user->id), ...$participations->map(fn ($j) => \App\Realtime\Channel::competition($j['competition']->id))->all()]" />
</x-layouts.portal>
