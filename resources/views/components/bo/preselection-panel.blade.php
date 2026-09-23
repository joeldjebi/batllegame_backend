@props(['competition', 'organizer', 'canUpdate' => false, 'canRun' => false])

@php
    use App\Enums\PerformanceStatus;
    use App\Enums\PreselectionState;

    $preselection = $competition->preselection;
    $state = $preselection?->state();
    $rules = $preselection?->rules ?? \App\Data\PreselectionRules::defaults();
    $entries = $preselection ? $preselection->entries->sortBy(fn ($e) => [$e->rank ?? PHP_INT_MAX, -$e->likes_count]) : collect();
    $approved = $entries->where('status', PerformanceStatus::Approved);
    $toReview = $entries->whereIn('status', [PerformanceStatus::Pending, PerformanceStatus::Processing]);
    $eligible = $competition->participants->whereIn('status', [\App\Enums\ParticipantStatus::Registered])->count();
    $fmtDate = fn ($d) => $d?->translatedFormat('d M Y, H:i');
@endphp

<div class="grid gap-6 xl:grid-cols-3">
    <x-ui.card :title="$preselection ? 'Présélection' : 'Organiser une présélection'" icon="funnel" class="xl:col-span-1"
        :description="$preselection ? null : 'Les artistes inscrits soumettent une prestation ; le public like (1 like par compétition) et le jury note ; les meilleurs sont retenus.'">
        @if ($preselection)
            <x-slot:actions><x-ui.badge :value="$state" /></x-slot:actions>
        @endif
        <form method="POST" action="{{ route('organizers.competitions.preselection.update', [$organizer, $competition]) }}" class="space-y-4"
            x-data="{ like: {{ (int) old('rules.like_weight', $rules->likeWeight) }} }">
            @csrf @method('PUT')
            <fieldset @disabled(! $canUpdate || $state === PreselectionState::Published) class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <x-ui.input name="starts_at" type="datetime-local" label="Début (soumissions et likes)" :value="$preselection?->starts_at" required />
                    <x-ui.input name="ends_at" type="datetime-local" label="Fin de la présélection" :value="$preselection?->ends_at" required />
                </div>
                <fieldset @disabled($preselection?->rulesFrozen()) class="space-y-4">
                    <x-ui.input name="rules[selection_size]" type="number" min="2" max="1024" label="Nombre d'artistes retenus" :value="$rules->selectionSize" required hint="Participants de l'événement après la présélection." />
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-white/[0.03]">
                        <div class="flex justify-between text-sm font-medium">
                            <span class="text-fuchsia-600 dark:text-fuchsia-300">Likes <span x-text="like"></span> %</span>
                            <span class="text-brand-700 dark:text-brand-300">Jury <span x-text="100 - like"></span> %</span>
                        </div>
                        <input type="range" min="0" max="100" step="5" x-model.number="like" class="mt-3 w-full accent-brand-600">
                        <input type="hidden" name="rules[like_weight]" :value="like">
                        <input type="hidden" name="rules[jury_weight]" :value="100 - like">
                    </div>
                    <div class="flex flex-wrap gap-4">
                        @foreach (\App\Enums\MediaType::cases() as $type)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rules[media_types][]" value="{{ $type->value }}" @checked(in_array($type, $rules->mediaTypes, true)) class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $type->label() }}</label>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.input name="rules[media_max_duration]" type="number" min="10" label="Durée max." :value="$rules->mediaMaxDuration" suffix="sec" />
                        <x-ui.input name="rules[media_max_size_mb]" type="number" min="1" label="Taille max." :value="$rules->mediaMaxSizeMb" suffix="Mo" />
                    </div>
                    @if ($preselection?->rulesFrozen())
                        <p class="text-xs text-slate-500">La présélection a commencé : seules les dates restent modifiables.</p>
                    @endif
                </fieldset>
                @if ($canUpdate && $state !== PreselectionState::Published)
                    <x-ui.button type="submit" class="w-full" icon="check">{{ $preselection ? 'Enregistrer' : 'Créer la présélection' }}</x-ui.button>
                @endif
            </fieldset>
        </form>
    </x-ui.card>

    <div class="space-y-6 xl:col-span-2">
        @if (! $preselection)
            <x-ui.empty icon="funnel" title="Pas de présélection" description="Sans présélection, vous validez les inscriptions à la main dans l'onglet Participants." />
        @else
            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.stat label="Période" :value="$state->label()" icon="calendar-days" tone="blue" :hint="$fmtDate($preselection->starts_at).' → '.$fmtDate($preselection->ends_at)" />
                <x-ui.stat label="Prestations" :value="$entries->count().' / '.$eligible" icon="film" :hint="$toReview->count().' à valider'" />
                <x-ui.stat label="Likes" :value="$entries->sum('likes_count')" icon="heart" tone="red" :hint="$rules->likeWeight.' % du score'" />
                <x-ui.stat label="À retenir" :value="$rules->selectionSize" icon="trophy" tone="green" :hint="'Jury '.$rules->juryWeight.' % du score'" />
            </div>

            <x-ui.card title="Classement" icon="list-bullet" :padding="false">
                <x-slot:actions>
                    @if ($canRun && $state !== PreselectionState::Published)
                        <form method="POST" action="{{ route('organizers.competitions.preselection.rank', [$organizer, $competition]) }}">@csrf<x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path">Recalculer</x-ui.button></form>
                    @endif
                    @if ($canUpdate && $state === PreselectionState::Closed)
                        <x-ui.confirm :action="route('organizers.competitions.preselection.publish', [$organizer, $competition])" :danger="false" icon="trophy"
                            title="Publier la sélection ?" :message="'Les '.$rules->selectionSize.' meilleurs artistes deviennent les participants de la compétition, les autres sont « non retenus ». Action définitive.'" confirm="Publier">
                            <x-ui.button size="sm" icon="trophy">Publier la sélection</x-ui.button>
                        </x-ui.confirm>
                    @endif
                </x-slot:actions>
                <div class="p-5">
                    @if ($entries->isEmpty())
                        <x-ui.empty icon="film" title="Aucune prestation" :description="$state === PreselectionState::Scheduled ? 'Les soumissions ouvriront le '.$fmtDate($preselection->starts_at).'.' : 'Les artistes inscrits peuvent soumettre depuis leur espace.'" />
                    @else
                        <x-ui.table>
                            <x-slot:head><th>#</th><th>Artiste</th><th>Prestation</th><th>Likes</th><th>Jury</th><th>Score</th><th></th></x-slot:head>
                            @foreach ($entries as $entry)
                                @php($cut = $entry->rank !== null && $entry->rank === $rules->selectionSize)
                                <tr @class(['bg-emerald-50/50 dark:bg-emerald-500/5' => $entry->rank !== null && $entry->rank <= $rules->selectionSize, 'border-b-2 border-emerald-500' => $cut])>
                                    <td>
                                        <span @class(['grid size-7 place-items-center rounded-full text-xs font-bold tabular-nums', 'bg-emerald-500 text-white' => $entry->rank && $entry->rank <= $rules->selectionSize, 'bg-slate-100 text-slate-500 dark:bg-white/10' => ! ($entry->rank && $entry->rank <= $rules->selectionSize)])>{{ $entry->rank ?? '–' }}</span>
                                    </td>
                                    <td>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $entry->participant->stage_name }}</p>
                                        <div class="mt-1 flex gap-1">
                                            <x-ui.badge :value="$entry->status" />
                                            @if ($entry->selected === true)<x-ui.badge tone="green" icon="trophy" :dot="false">Retenu</x-ui.badge>@elseif ($entry->selected === false)<x-ui.badge tone="gray" :dot="false">Non retenu</x-ui.badge>@endif
                                        </div>
                                    </td>
                                    <td class="w-72">
                                        @if ($entry->media_path)<x-bo.media-player :performance="$entry" class="max-h-40" />@endif
                                        @if ($entry->rejection_reason)<p class="mt-1 text-xs text-rose-600">{{ $entry->rejection_reason }}</p>@endif
                                    </td>
                                    <td class="font-display font-bold tabular-nums">{{ $entry->likes_count }}</td>
                                    <td class="tabular-nums">{{ $entry->jury_score !== null ? number_format($entry->jury_score, 1, ',', '') : '—' }}</td>
                                    <td class="font-display font-bold tabular-nums">{{ $entry->final_score !== null ? number_format($entry->final_score, 1, ',', '') : '—' }}</td>
                                    <td>
                                        @if ($canRun && $entry->status === PerformanceStatus::Pending)
                                            <div class="flex flex-col gap-1.5" x-data="{ rejecting: false }">
                                                <form method="POST" action="{{ route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]) }}" x-show="! rejecting">
                                                    @csrf @method('PATCH')<input type="hidden" name="decision" value="approve">
                                                    <x-ui.button type="submit" size="xs" icon="check" class="w-full">Valider</x-ui.button>
                                                </form>
                                                <x-ui.button size="xs" variant="danger-soft" icon="x-mark" x-show="! rejecting" x-on:click="rejecting = true">Rejeter</x-ui.button>
                                                <form method="POST" action="{{ route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]) }}" x-show="rejecting" x-cloak class="flex gap-1">
                                                    @csrf @method('PATCH')<input type="hidden" name="decision" value="reject">
                                                    <input name="reason" required placeholder="Motif" class="w-28 rounded-lg border-0 bg-slate-50 py-1 text-xs ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                                    <x-ui.button type="submit" size="xs" variant="danger">OK</x-ui.button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                        <p class="mt-3 text-xs text-slate-500">Score = jury × {{ $rules->juryWeight }} % + likes × {{ $rules->likeWeight }} % (likes rapportés à la prestation la plus likée). La ligne verte marque la limite des {{ $rules->selectionSize }} artistes retenus.</p>
                    @endif
                </div>
            </x-ui.card>
        @endif
    </div>
</div>
