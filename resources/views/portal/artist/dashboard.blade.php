@php
    use App\Enums\ParticipantStatus;
    use App\Enums\PerformanceStatus;
    use App\Enums\PreselectionState;
@endphp

<x-layouts.portal title="Mon espace">
    <x-ui.page-header :title="'Bonjour, '.Str::before(auth('member')->user()->name, ' ').' 🎤'" description="Suivez vos compétitions et envoyez vos prestations à chaque étape." />

    <h2 class="mb-4 font-display text-lg font-semibold">Mes compétitions</h2>
    @if ($participations->isEmpty())
        <x-ui.empty icon="microphone" title="Aucune participation" description="Inscrivez-vous à une compétition ouverte ci-dessous." class="mb-10" />
    @else
        <div class="mb-10 space-y-4">
            @foreach ($participations as ['participant' => $participant, 'stage' => $stage, 'submission' => $submission, 'playing' => $playing])
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div>
                            <p class="font-display font-semibold">{{ $participant->competition->name }}</p>
                            <p class="text-sm text-slate-500">{{ $participant->competition->organizer->name }} · nom de scène : <strong>{{ $participant->stage_name }}</strong></p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.badge :value="$participant->competition->status" />
                            <x-ui.badge :value="$participant->status" />
                        </div>
                    </div>

                    @if ($participant->status === ParticipantStatus::PaymentPending)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-amber-50/60 px-5 py-4 dark:border-white/5 dark:bg-amber-500/5">
                            <p class="text-sm text-amber-800 dark:text-amber-200"><strong>Inscription à confirmer :</strong> réglez les frais de {{ number_format($participant->competition->entry_fee, 0, ',', ' ') }} {{ $participant->competition->currency }} pour devenir artiste de cette compétition.</p>
                            <x-ui.button size="sm" :href="route('artist.competitions.payment', $participant->competition)" icon="credit-card">Payer</x-ui.button>
                        </div>
                    @elseif ($preselection = $participant->competition->preselection)
                        @php($state = $preselection->state())
                        @php($entry = $participant->preselectionEntry)
                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-white/5 dark:bg-white/[0.02]">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm"><span class="font-semibold">Présélection</span> <x-ui.badge :value="$state" class="ml-1" /></p>
                                <p class="text-sm text-slate-500">{{ $preselection->starts_at->translatedFormat('d M, H:i') }} → {{ $preselection->ends_at->translatedFormat('d M, H:i') }} · {{ $preselection->rules->selectionSize }} artistes retenus</p>
                            </div>

                            @if ($state === PreselectionState::Published)
                                @if ($participant->status === ParticipantStatus::Validated)
                                    <p class="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"><x-ui.icon name="trophy" class="size-5" /> Félicitations, vous êtes sélectionné{{ $entry?->rank ? ' ('.$entry->rank.'e)' : '' }} !</p>
                                @else
                                    <p class="mt-3 text-sm text-slate-500">Vous n'avez pas été retenu cette fois{{ $entry?->rank ? ' ('.$entry->rank.'e)' : '' }}. Merci pour votre participation !</p>
                                @endif
                            @else
                                @if ($entry)
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                        <span>Votre prestation :</span> <x-ui.badge :value="$entry->status" />
                                        <span class="text-slate-500">{{ $entry->likes_count }} like(s)</span>
                                        @if ($entry->rejection_reason)<span class="text-rose-600">{{ $entry->rejection_reason }}</span>@endif
                                    </div>
                                    @if ($entry->media_path)<x-bo.media-player :performance="$entry" class="mt-3 max-w-lg" />@endif
                                @endif

                                @if ($state === PreselectionState::Open && $participant->status === ParticipantStatus::Registered)
                                    @php($prules = $preselection->rules)
                                    <form method="POST" action="{{ route('artist.competitions.preselection.submit', $participant->competition) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                                        @csrf
                                        <x-ui.field label="{{ $entry ? 'Remplacer ma prestation' : 'Envoyer ma prestation de présélection' }}" :hint="collect($prules->mediaTypes)->map->label()->implode(' ou ').' · '.gmdate('i:s', $prules->mediaMaxDuration).' max · '.$prules->mediaMaxSizeMb.' Mo max'" class="flex-1">
                                            <input type="file" name="media" required accept="{{ implode(',', $prules->acceptedMimeTypes()) }}" class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                                        </x-ui.field>
                                        <x-ui.button type="submit" icon="arrow-up-tray">Envoyer</x-ui.button>
                                    </form>
                                @elseif ($state === PreselectionState::Scheduled)
                                    <p class="mt-2 text-sm text-slate-500">Les soumissions ouvriront le {{ $preselection->starts_at->translatedFormat('l d F à H:i') }}.</p>
                                @elseif ($state === PreselectionState::Closed)
                                    <p class="mt-2 text-sm text-slate-500">Présélection terminée : résultats bientôt publiés par l'organisateur.</p>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if ($stage && $playing)
                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-white/5 dark:bg-white/[0.02]">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm"><span class="font-semibold">{{ $stage->name }}</span> <x-ui.badge :value="$stage->status" class="ml-1" /></p>
                                @if ($stage->isOnline() && $stage->submission_deadline)
                                    <p class="text-sm text-slate-500">Date limite : <strong>{{ $stage->submission_deadline->translatedFormat('l d F, H:i') }}</strong></p>
                                @endif
                            </div>

                            @if (! $stage->isOnline())
                                <p class="mt-2 text-sm text-slate-500">Étape en présentiel : rendez-vous sur scène, le vote s'ouvrira en direct.</p>
                            @else
                                @if ($submission)
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                        <span>Votre prestation :</span> <x-ui.badge :value="$submission->status" />
                                        @if ($submission->status === PerformanceStatus::Rejected)<span class="text-rose-600">{{ $submission->rejection_reason }}</span>@endif
                                    </div>
                                    @if ($submission->media_path)<x-bo.media-player :performance="$submission" class="mt-3 max-w-lg" />@endif
                                @endif

                                @if ($stage->acceptsSubmissions())
                                    @php($rules = $stage->phase->rules)
                                    <form method="POST" action="{{ route('artist.competitions.stages.submit', [$participant->competition, $stage]) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                                        @csrf
                                        <x-ui.field label="{{ $submission ? 'Remplacer ma prestation' : 'Envoyer ma prestation' }}" :hint="collect($rules->mediaTypes)->map->label()->implode(' ou ').' · '.gmdate('i:s', $rules->mediaMaxDuration).' max · '.$rules->mediaMaxSizeMb.' Mo max'" class="flex-1">
                                            <input type="file" name="media" required accept="{{ implode(',', $rules->acceptedMimeTypes()) }}" class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                                        </x-ui.field>
                                        <x-ui.button type="submit" icon="arrow-up-tray">Envoyer</x-ui.button>
                                    </form>
                                @elseif (! $submission)
                                    <p class="mt-2 text-sm text-slate-500">Les soumissions ne sont pas encore ouvertes pour cette étape.</p>
                                @endif
                            @endif
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <h2 class="mb-4 font-display text-lg font-semibold">Compétitions ouvertes aux inscriptions</h2>
    @if ($open->isEmpty())
        <x-ui.empty icon="calendar" title="Aucune inscription ouverte" description="Revenez bientôt : de nouvelles compétitions arrivent." />
    @else
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($open as $competition)
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$competition->discipline->icon()" class="size-5" /></span>
                        <x-ui.badge tone="gray" :dot="false" :icon="$competition->mode->icon()">{{ $competition->mode->label() }}</x-ui.badge>
                    </div>
                    <h3 class="mt-4 font-display font-semibold">{{ $competition->name }}</h3>
                    <p class="text-sm text-slate-500">{{ $competition->organizer->name }} · {{ $competition->discipline->label() }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ $competition->participants_count }}{{ $competition->max_participants ? ' / '.$competition->max_participants : '' }} inscrits
                        @if ($competition->registration_ends_at) · jusqu'au {{ $competition->registration_ends_at->translatedFormat('d M') }}@endif
                        · {{ $competition->entry_fee ? number_format($competition->entry_fee, 0, ',', ' ').' '.$competition->currency : 'Gratuit' }}
                    </p>
                    <form method="POST" action="{{ route('artist.competitions.register', $competition) }}" class="mt-4 flex gap-2">
                        @csrf
                        <input name="stage_name" required placeholder="Votre nom de scène" value="{{ auth('member')->user()->name }}" class="min-w-0 flex-1 rounded-xl border-0 bg-white py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                        <x-ui.button type="submit" icon="user-plus">S'inscrire</x-ui.button>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.portal>
