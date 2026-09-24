@props(['phase', 'organizer', 'competition', 'canUpdate' => false])

{{-- Planned calendar of a phase not started yet: one row per stage up to the final (App\Services\Competition\PhaseCalendar). --}}
@php
    use App\Enums\CompetitionMode;
    use App\Services\Competition\PhaseCalendar;

    $planned = PhaseCalendar::planned($phase);
    $online = $phase->effectiveMode() === CompetitionMode::Online;
    $timezone = $competition->settings->timezone;
    $modal = 'phase-calendar-'.$phase->id;
    $fmt = fn ($date) => $date?->copy()->timezone($timezone)->translatedFormat('d M, H:i');
    $local = fn ($date) => $date?->copy()->timezone($timezone)->format('Y-m-d\TH:i');
    $entrants = PhaseCalendar::expectedEntrants($phase);
@endphp

@if ($planned === [])
    <x-ui.empty icon="rocket-launch" title="Prête à démarrer" description="Le calendrier de chaque tour se programme après le démarrage, étape par étape." />
@else
    <div class="rounded-xl bg-slate-50/80 p-4 ring-1 ring-slate-900/5 dark:bg-white/[0.02] dark:ring-white/10">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white"><x-ui.icon name="calendar-days" class="size-5 text-brand-600" /> Calendrier prévu</p>
                <p class="text-xs text-slate-500">
                    @if ($phase->type === \App\Enums\PhaseType::SingleElimination && $entrants) {{ $entrants }} participants attendus · @endif
                    Appliqué automatiquement au démarrage de la phase.
                </p>
            </div>
            @if ($canUpdate)
                <x-ui.button size="sm" variant="secondary" icon="calendar-days" x-data x-on:click="$dispatch('open-modal', '{{ $modal }}')">Planifier</x-ui.button>
            @endif
        </div>
        <ol class="space-y-2">
            @foreach ($planned as $stage)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $loop->iteration }}</span>
                    <span class="min-w-0 flex-1 font-semibold text-slate-800 dark:text-slate-100">{{ $stage['name'] }}</span>
                    <span class="flex flex-wrap gap-x-3 text-xs text-slate-500">
                        @if ($online)<span>Envoi : {{ $fmt($stage['submission_deadline']) ?? '—' }}</span>@endif
                        <span>Vote : {{ $fmt($stage['voting_opens_at']) ?? ($online ? 'après l\'envoi' : 'en direct') }} → {{ $fmt($stage['voting_closes_at']) ?? 'clôture manuelle' }}</span>
                        @if ($stage['deliberation_minutes'])<span>Jury : +{{ \Carbon\CarbonInterval::minutes($stage['deliberation_minutes'])->cascade()->forHumans(short: true) }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

    @if ($canUpdate)
        @push('modals')
            <x-ui.modal :name="$modal" :title="'Calendrier · Phase '.$phase->position" icon="calendar-days" max-width="3xl"
                :description="$online ? 'Pour chaque tour : date limite d\'envoi des vidéos, fin du vote du public et temps de délibération du jury. Sans vidéo à la date limite : forfait.' : 'Pour chaque tour : ouverture et fin du vote, puis temps de délibération du jury. Vous pouvez aussi ouvrir le vote en direct.'">
                <form method="POST" action="{{ route('organizers.competitions.phases.calendar', [$organizer, $competition, $phase]) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="_form" value="{{ $modal }}">
                    <div class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                        @foreach ($planned as $stage)
                            <fieldset class="rounded-xl p-3 ring-1 ring-slate-200 dark:ring-white/10">
                                <legend class="px-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $stage['name'] }}</legend>
                                <div @class(['grid gap-3', 'sm:grid-cols-4' => $online, 'sm:grid-cols-3' => ! $online])>
                                    @if ($online)
                                        <x-ui.input :name="'calendar['.$stage['name'].'][submission_deadline]'" type="datetime-local" label="Limite d'envoi" :value="$local($stage['submission_deadline'])" />
                                    @endif
                                    <x-ui.input :name="'calendar['.$stage['name'].'][voting_opens_at]'" type="datetime-local" label="Ouverture du vote" :value="$local($stage['voting_opens_at'])" :hint="$online ? 'Vide : dès la limite.' : 'Vide : en direct.'" />
                                    <x-ui.input :name="'calendar['.$stage['name'].'][voting_closes_at]'" type="datetime-local" label="Fin du vote" :value="$local($stage['voting_closes_at'])" />
                                    <x-ui.input :name="'calendar['.$stage['name'].'][deliberation_minutes]'" type="number" min="0" max="10080" label="Délibération" :value="$stage['deliberation_minutes']" suffix="min" />
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $modal }}')">Annuler</x-ui.button>
                        <x-ui.button type="submit" icon="check">Enregistrer le calendrier</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endpush
    @endif
@endif
