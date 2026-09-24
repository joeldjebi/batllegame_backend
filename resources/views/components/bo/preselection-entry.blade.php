@props(['entry', 'competition', 'organizer', 'rules', 'canRun' => false, 'judgesCount' => 0])

{{-- One row of the pre-selection ranking + its detail modal (player, provenance, scores, review). --}}
@php
    use App\Enums\PerformanceStatus;

    $modal = 'entry-'.$entry->id;
    $selectedZone = $entry->rank !== null && $entry->rank <= $rules->selectionSize;
    $paid = $entry->participant->hasPaid();
    $reviewable = $canRun && $entry->status === PerformanceStatus::Pending;
    $review = route('organizers.competitions.preselection.entries.review', [$organizer, $competition, $entry]);
    $number = fn ($v) => $v !== null ? number_format($v, 1, ',', '') : '—';
    $timezone = $competition->settings->timezone;
    $filter = match ($entry->status) { PerformanceStatus::Pending, PerformanceStatus::Processing => 'review', PerformanceStatus::Approved => 'approved', PerformanceStatus::Rejected => 'rejected' };
    $judged = $entry->scores->pluck('judge_id')->unique()->count();
@endphp

<li
    @class([
        'grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2.5 px-4 py-4 sm:px-5 lg:grid-cols-[2.5rem_minmax(0,1.6fr)_minmax(0,1.5fr)_4.5rem_4.5rem_4.5rem_auto] lg:gap-4',
        'bg-emerald-50/60 dark:bg-emerald-500/5' => $selectedZone,
        'border-b-2 border-emerald-500' => $entry->rank !== null && $entry->rank === $rules->selectionSize,
    ])>
    <span @class([
        'grid size-9 place-items-center rounded-full font-display text-sm font-bold tabular-nums',
        'bg-emerald-500 text-white' => $selectedZone,
        'bg-slate-100 text-slate-500 dark:bg-white/10' => ! $selectedZone,
    ])>{{ $entry->rank ?? '–' }}</span>

    <div class="flex min-w-0 items-start gap-3">
        <x-ui.avatar :name="$entry->participant->stage_name" :src="$entry->participant->user?->avatarUrl()" size="md" />
        <div class="min-w-0">
        <button type="button" x-on:click="$dispatch('open-modal', @js($modal))" class="block max-w-full truncate text-left font-semibold text-slate-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-300">{{ $entry->participant->stage_name }}</button>
        <div class="mt-1 flex flex-wrap gap-1">
            <x-ui.badge :value="$entry->status" />
            @unless ($paid)<x-ui.badge tone="red" icon="banknotes" :dot="false">Frais non payés</x-ui.badge>@endunless
            @if ($entry->selected === true)<x-ui.badge tone="green" icon="trophy" :dot="false">Retenu</x-ui.badge>@elseif ($entry->selected === false)<x-ui.badge tone="gray" :dot="false">Non retenu</x-ui.badge>@endif
        </div>
        </div>
    </div>

    {{-- Actions: top-right on mobile, last column on desktop --}}
    <div class="col-start-3 row-start-1 flex items-center justify-end gap-1.5 lg:col-start-7">
        @if ($reviewable && $paid)
            <form method="POST" action="{{ $review }}" class="hidden sm:block" x-data="ajaxForm" x-on:submit.prevent="send">
                @csrf @method('PATCH')<input type="hidden" name="decision" value="approve">
                <x-ui.button type="submit" size="sm" icon="check">Valider</x-ui.button>
            </form>
        @endif
        <x-ui.button size="sm" variant="secondary" icon="eye" x-on:click="$dispatch('open-modal', '{{ $modal }}')">Voir</x-ui.button>
    </div>

    <div class="col-span-3 col-start-1 sm:col-start-2 lg:col-span-1 lg:col-start-3 lg:row-start-1">
        <x-bo.media-provenance :media="$entry" :timezone="$timezone" compact />
    </div>

    <dl class="col-span-3 col-start-1 grid grid-cols-3 gap-2 text-center sm:col-start-2 lg:contents">
        <div class="rounded-xl bg-slate-50 px-2 py-1.5 lg:bg-transparent lg:p-0 dark:bg-white/5 lg:dark:bg-transparent"><dt class="text-[11px] text-slate-400 lg:hidden">Likes</dt><dd class="font-display font-bold tabular-nums">{{ $entry->likes_count }}</dd></div>
        <div class="rounded-xl bg-slate-50 px-2 py-1.5 lg:bg-transparent lg:p-0 dark:bg-white/5 lg:dark:bg-transparent"><dt class="text-[11px] text-slate-400 lg:hidden">Jury</dt><dd class="tabular-nums">{{ $number($entry->jury_score) }}</dd></div>
        <div class="rounded-xl bg-slate-50 px-2 py-1.5 lg:bg-transparent lg:p-0 dark:bg-white/5 lg:dark:bg-transparent"><dt class="text-[11px] text-slate-400 lg:hidden">Score</dt><dd class="font-display font-bold tabular-nums">{{ $number($entry->final_score) }}</dd></div>
    </dl>
</li>

@push('modals')
    <x-ui.modal :name="$modal" :title="$entry->participant->stage_name" icon="film" max-width="4xl"
        :description="($entry->rank ? 'Rang '.$entry->rank.' · ' : '').$entry->status->label().' · envoyée le '.$entry->uploadedAt()->copy()->setTimezone($timezone)->translatedFormat('d M Y à H:i')">
        <div class="grid gap-6 md:grid-cols-5">
            <div class="space-y-3 md:col-span-3">
                @if ($entry->media_path)
                    <x-bo.media-player :performance="$entry" preload="none" />
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span class="min-w-0 truncate">
                            {{ $entry->media_type?->label() }}
                            @if ($entry->duration_seconds) · {{ gmdate('i:s', $entry->duration_seconds) }}@endif
                            · {{ number_format(($entry->size_bytes ?? 0) / 1048576, 1, ',', ' ') }} Mo
                            @if ($entry->original_name) · <span title="{{ $entry->original_name }}">{{ Str::limit($entry->original_name, 40) }}</span>@endif
                        </span>
                        <a href="{{ $entry->mediaUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-brand-700 dark:text-brand-300"><x-ui.icon name="arrow-top-right-on-square" variant="m" class="size-4" /> Ouvrir le fichier</a>
                    </div>
                @endif

                <div class="rounded-xl ring-1 ring-slate-200 p-4 dark:ring-white/10">
                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100"><x-ui.icon name="finger-print" class="size-4 text-brand-600" /> Provenance du fichier</p>
                    <x-bo.media-provenance :media="$entry" :timezone="$timezone" expanded />
                </div>
            </div>

            <div class="space-y-4 md:col-span-2">
                {{-- The artist: opens their « fiche » (participants tab, same page) --}}
                <button type="button" x-on:click="$dispatch('close-modal', @js($modal)); $dispatch('open-modal', @js('participant-'.$entry->participant_id))"
                    class="flex w-full items-center gap-3 rounded-xl bg-slate-50 p-3 text-left ring-1 ring-slate-900/5 transition hover:ring-brand-300 dark:bg-white/5 dark:ring-white/10">
                    <x-ui.avatar :name="$entry->participant->stage_name" :src="$entry->participant->user?->avatarUrl()" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ $entry->participant->user?->name }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $entry->participant->user?->phone }}{{ $entry->participant->user?->locationLabel() ? ' · '.$entry->participant->user->locationLabel() : '' }}</span>
                    </span>
                    <span class="text-xs font-semibold text-brand-600 dark:text-brand-300">Fiche</span>
                </button>
                <dl class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><dt class="text-xs text-slate-500">Rang</dt><dd class="font-display text-xl font-bold">{{ $entry->rank ?? '—' }}<span class="text-sm font-medium text-slate-400"> / {{ $rules->selectionSize }} retenus</span></dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><dt class="text-xs text-slate-500">Likes</dt><dd class="font-display text-xl font-bold">{{ $entry->likes_count }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><dt class="text-xs text-slate-500">Note du jury</dt><dd class="font-display text-xl font-bold">{{ $number($entry->jury_score) }}</dd><dd class="text-xs text-slate-400">{{ $judged }} / {{ $judgesCount }} juré(s)</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><dt class="text-xs text-slate-500">Score final</dt><dd class="font-display text-xl font-bold">{{ $number($entry->final_score) }}</dd></div>
                </dl>

                {{-- Notes of each judge (final), with « Rouvrir » after a mistake --}}
                @if ($entry->scores->isNotEmpty())
                    <div class="space-y-2">
                        <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Notes des jurés</p>
                        @foreach ($entry->scores->groupBy('judge_id') as $judgeId => $rows)
                            @php
                                $scorer = $competition->judges->firstWhere('id', $judgeId);
                            @endphp
                            <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-white/5">
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $scorer?->user?->name ?? 'Juré' }}</span>
                                <span class="font-display font-bold tabular-nums">{{ rtrim(rtrim(number_format($rows->sum('score'), 1, ',', ''), '0'), ',') }} pts</span>
                                @if ($canRun && $scorer && $entry->preselection->acceptsScores())
                                    <form method="POST" action="{{ route('organizers.competitions.preselection.entries.scores.destroy', [$organizer, $competition, $entry, $scorer]) }}" x-data="ajaxForm" x-on:submit.prevent="if (confirm(@js('Effacer la note de '.$scorer->user->name.' pour qu\'il la refasse ?'))) send()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-lg px-2 py-1 text-xs font-semibold text-brand-600 hover:bg-white dark:text-brand-300 dark:hover:bg-white/10">Rouvrir</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @unless ($paid)
                    <p class="flex items-start gap-2 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"><x-ui.icon name="banknotes" variant="m" class="mt-0.5 size-4 shrink-0" /> Frais d'inscription non payés : validation possible après paiement.</p>
                @endunless
                @if ($entry->rejection_reason)
                    <p class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"><strong>Motif du rejet :</strong> {{ $entry->rejection_reason }}</p>
                @endif
                @if ($entry->status === PerformanceStatus::Processing)
                    <p class="flex items-center gap-2 rounded-xl bg-sky-50 p-3 text-sm text-sky-800 dark:bg-sky-500/10 dark:text-sky-200"><x-ui.icon name="arrow-path" variant="m" class="size-4 animate-spin" /> Analyse du média en cours.</p>
                @endif

                @if ($reviewable)
                    <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-white/10" x-data="{ rejecting: false }">
                        @if ($paid)
                            <form method="POST" action="{{ $review }}" x-show="! rejecting" x-data="ajaxForm({ modal: @js($modal) })" x-on:submit.prevent="send">
                                @csrf @method('PATCH')<input type="hidden" name="decision" value="approve">
                                <x-ui.button type="submit" size="lg" icon="check" class="w-full">Valider la prestation</x-ui.button>
                            </form>
                        @endif
                        <x-ui.button variant="danger-soft" icon="x-mark" class="w-full" x-show="! rejecting" x-on:click="rejecting = true">Rejeter…</x-ui.button>
                        <form method="POST" action="{{ $review }}" x-show="rejecting" x-cloak class="space-y-2" x-data="ajaxForm({ modal: @js($modal) })" x-on:submit.prevent="send">
                            @csrf @method('PATCH')<input type="hidden" name="decision" value="reject">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="reason-{{ $entry->id }}">Motif (visible par l'artiste)</label>
                            <textarea id="reason-{{ $entry->id }}" name="reason" required rows="3" maxlength="250" placeholder="Ex. vidéo téléchargée depuis TikTok, enregistrement antérieur à la compétition…"
                                class="block w-full rounded-xl border-0 bg-white text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-rose-500 dark:bg-white/5 dark:ring-white/10"></textarea>
                            <div class="flex gap-2">
                                <x-ui.button variant="secondary" class="flex-1" x-on:click="rejecting = false">Annuler</x-ui.button>
                                <x-ui.button type="submit" variant="danger" icon="x-mark" class="flex-1">Rejeter</x-ui.button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </x-ui.modal>
@endpush
