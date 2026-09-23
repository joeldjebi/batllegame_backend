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
            <x-ui.avatar :name="$user->name" size="lg" class="!ring-white/30" />
            <div class="min-w-0">
                <p class="text-sm text-white/70">Espace artiste</p>
                <h1 class="truncate font-display text-2xl font-extrabold sm:text-3xl">Salut, {{ Str::before($user->name, ' ') }} 🎤</h1>
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
        {{-- Action center --}}
        @if ($actions->isNotEmpty())
            <section class="space-y-4">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><span class="grid size-6 place-items-center rounded-full bg-rose-500 text-xs text-white">{{ $actions->count() }}</span> À faire maintenant</h2>
                @foreach ($actions as $journey)
                    @if ($journey['action'] === 'pay')
                        <x-portal.payment-pitch :competition="$journey['competition']" :deadline="$journey['deadline']" />
                    @else
                        @php($isPreselection = $journey['action'] === 'preselection')
                        @php($mediaRules = $isPreselection ? $journey['preselection']->rules : $journey['stage']->phase->rules)
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

                            @php($current = $isPreselection ? $journey['entry'] : $journey['submission'])
                            @if ($current?->status === PerformanceStatus::Rejected)
                                <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300"><strong>Prestation refusée :</strong> {{ $current->rejection_reason }}. Envoie une nouvelle version.</p>
                            @endif

                            <form method="POST" enctype="multipart/form-data" class="mt-5"
                                action="{{ $isPreselection ? route('artist.competitions.preselection.submit', $journey['competition']) : route('artist.competitions.stages.submit', [$journey['competition'], $journey['stage']]) }}">
                                @csrf
                                <x-portal.dropzone :accept="implode(',', $mediaRules->acceptedMimeTypes())" :max-mb="$mediaRules->mediaMaxSizeMb"
                                    :hint="collect($mediaRules->mediaTypes)->map->label()->implode(' ou ').' · '.gmdate('i:s', $mediaRules->mediaMaxDuration).' max · '.$mediaRules->mediaMaxSizeMb.' Mo max'" />
                            </form>
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
                <div class="space-y-4">
                    @foreach ($participations as $journey)
                        @php($participant = $journey['participant'])
                        @php($competition = $journey['competition'])
                        <article x-data="{ open: false }" class="overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10">
                            <div class="flex items-start gap-4 p-5">
                                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-6" /></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-display text-base font-bold sm:text-lg">{{ $competition->name }}</h3>
                                    <p class="truncate text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $participant->stage_name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <x-ui.badge :value="$participant->status" />
                                        <x-ui.badge :value="$competition->status" />
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-slate-100 px-5 py-4 dark:border-white/5">
                                <x-portal.stepper :steps="$journey['steps']" />
                            </div>

                            {{-- Current status --}}
                            <div class="space-y-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 text-sm dark:border-white/5 dark:bg-white/[0.02]">
                                @if (! $journey['paid'])
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-slate-600 dark:text-slate-300"><x-ui.icon name="lock-closed" variant="m" class="mr-1 inline size-4 text-amber-500" /> Envoi de prestation verrouillé jusqu'au paiement ({{ $fmtFee($competition) }}).</p>
                                        <x-ui.button size="sm" :href="route('artist.competitions.payment', $competition)" icon="credit-card">Débloquer</x-ui.button>
                                    </div>
                                @elseif ($journey['state'] === PreselectionState::Published)
                                    @if ($participant->status === ParticipantStatus::Validated)
                                        <p class="flex items-center gap-2 font-semibold text-emerald-700 dark:text-emerald-300"><x-ui.icon name="trophy" class="size-5" /> Sélectionné{{ $journey['entry']?->rank ? ' · '.$journey['entry']->rank.'e de la présélection' : '' }} ! Prépare-toi pour la compétition.</p>
                                    @else
                                        <p class="text-slate-500">Pas retenu cette fois{{ $journey['entry']?->rank ? ' ('.$journey['entry']->rank.'e)' : '' }}. Merci et à la prochaine !</p>
                                    @endif
                                @elseif ($journey['preselection'] && $journey['state'] !== PreselectionState::Published)
                                    @if ($journey['state'] === PreselectionState::Scheduled)
                                        <p class="text-slate-500">Présélection à partir du <strong>{{ $journey['preselection']->starts_at->translatedFormat('l d F à H:i') }}</strong>.</p>
                                    @elseif ($journey['action'] === 'preselection')
                                        <p class="flex items-center gap-2 font-medium text-brand-700 dark:text-brand-300"><x-ui.icon name="arrow-up-tray" variant="m" class="size-4" /> Prestation attendue avant le {{ $journey['preselection']->ends_at->translatedFormat('d F à H:i') }} (voir « À faire »).</p>
                                    @elseif ($journey['entry'] && $journey['entry']->status !== PerformanceStatus::Rejected)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>Ta prestation</span><x-ui.badge :value="$journey['entry']->status" />
                                            <span class="inline-flex items-center gap-1 text-slate-500"><x-ui.icon name="heart" variant="m" class="size-4 text-fuchsia-500" /> {{ $journey['entry']->likes_count }} like(s)</span>
                                        </div>
                                        @if ($journey['state'] === PreselectionState::Closed)<p class="text-slate-500">Présélection terminée : résultats bientôt.</p>@endif
                                    @endif
                                @elseif ($journey['playing'] && $journey['submission'])
                                    <div class="flex flex-wrap items-center gap-2"><span>{{ $journey['stage']->name }} :</span><x-ui.badge :value="$journey['submission']->status" /></div>
                                @elseif ($journey['stage'] && ! $journey['stage']->isOnline() && $journey['playing'])
                                    <p class="text-slate-500">{{ $journey['stage']->name }} en présentiel : rendez-vous sur scène.</p>
                                @else
                                    <p class="text-slate-500">Rien à faire pour le moment.</p>
                                @endif

                                @php($media = $journey['entry'] ?? $journey['submission'])
                                @if ($media?->media_path)
                                    <button type="button" x-on:click="open = ! open" class="flex items-center gap-1.5 text-xs font-semibold text-brand-600 dark:text-brand-300">
                                        <x-ui.icon name="play-circle" variant="m" class="size-4" /><span x-text="open ? 'Masquer ma prestation' : 'Revoir ma prestation'"></span>
                                    </button>
                                    <div x-show="open" x-collapse x-cloak><x-bo.media-player :performance="$media" class="max-w-xl" /></div>
                                @endif
                            </div>
                        </article>
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
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.portal>
