@php
    $name = $entry->participant->stage_name;
@endphp

<x-layouts.portal :title="$name.' · Présélection'">
    <x-ui.page-header :title="$name" :breadcrumbs="['Mes compétitions' => route('jury.dashboard'), $competition->name => route('jury.competitions.show', $competition), 'Présélection' => route('jury.competitions.preselection', $competition), $name => null]">
        <x-slot:description>
            <span class="tabular-nums">{{ $scored }} / {{ $total }} notées</span>
            @if ($mine->isNotEmpty())<x-ui.badge tone="green" icon="check" :dot="false">Notée</x-ui.badge>@endif
        </x-slot:description>
    </x-ui.page-header>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="space-y-4">
            <div class="overflow-hidden rounded-3xl bg-slate-950 shadow-lift">
                <x-bo.media-player :performance="$entry" />
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10">
                <x-ui.avatar :name="$name" :src="$entry->participant->user?->avatarUrl()" size="lg" />
                <div class="min-w-0">
                    <p class="truncate font-display text-lg font-bold">{{ $name }}</p>
                    <p class="text-sm text-slate-500">{{ $entry->media_type?->label() }}@if ($entry->duration_seconds) · {{ gmdate('i:s', $entry->duration_seconds) }}@endif</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 lg:sticky lg:top-24 dark:bg-slate-900/60 dark:ring-white/10">
            @if ($mine->isNotEmpty())
                <h2 class="flex items-center gap-2 font-display text-lg font-bold"><x-ui.icon name="lock-closed" variant="m" class="size-5 text-emerald-600" /> Tes notes (définitives)</h2>
                <dl class="mt-4 space-y-3">
                    @foreach ($criteria as $criterion)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-white/5">
                            <dt class="text-sm font-medium">{{ $criterion->name }}</dt>
                            <dd class="font-display font-bold tabular-nums">{{ rtrim(rtrim(number_format($mine->get($criterion->id)?->score ?? 0, 1, ',', ''), '0'), ',') }} / {{ $criterion->max_points }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-slate-500">Une erreur ? Demande à l'organisateur de rouvrir ta note.</p>
            @elseif ($canScore)
                <form method="POST" action="{{ route('jury.competitions.preselection.scores.store', [$competition, $entry]) }}" class="space-y-5"
                    x-data="{ confirming: false }" x-on:submit="if (! confirming) { $event.preventDefault(); confirming = true }">
                    @csrf
                    <h2 class="font-display text-lg font-bold">Ta note</h2>
                    @foreach ($criteria as $i => $criterion)
                        <div x-data="{ value: {{ (float) old('scores.'.$i.'.score', 0) }} }">
                            <input type="hidden" name="scores[{{ $i }}][criterion_id]" value="{{ $criterion->id }}">
                            <div class="flex items-center justify-between text-sm">
                                <label for="c{{ $criterion->id }}" class="font-medium">{{ $criterion->name }}</label>
                                <span class="font-display text-base font-bold tabular-nums"><span x-text="value"></span> / {{ $criterion->max_points }}</span>
                            </div>
                            <input id="c{{ $criterion->id }}" type="range" name="scores[{{ $i }}][score]" min="0" max="{{ $criterion->max_points }}" step="0.5" x-model.number="value" class="mt-2 w-full accent-brand-600">
                        </div>
                    @endforeach

                    <p x-show="confirming" x-cloak class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">Tes notes seront <strong>définitives</strong>. Confirme pour enregistrer.</p>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary" x-show="confirming" x-cloak x-on:click="confirming = false">Modifier</x-ui.button>
                        <x-ui.button type="submit" size="lg" class="flex-1" icon="check">
                            <span x-text="confirming ? 'Confirmer' : @js($next ? 'Enregistrer et suivante' : 'Enregistrer')"></span>
                        </x-ui.button>
                    </div>
                    @error('flow')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                </form>
            @else
                <p class="flex items-center gap-2 text-sm text-slate-500"><x-ui.icon name="lock-closed" variant="m" class="size-5" /> La délibération est close.</p>
            @endif

            <div class="mt-6 flex items-center justify-between gap-2 border-t border-slate-100 pt-4 text-sm dark:border-white/10">
                <a href="{{ route('jury.competitions.preselection', $competition) }}" class="inline-flex items-center gap-1 font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white"><x-ui.icon name="list-bullet" variant="m" class="size-4" /> La liste</a>
                @if ($next)
                    <a href="{{ route('jury.competitions.preselection.entries.show', [$competition, $next]) }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">{{ $mine->isNotEmpty() ? 'Prochaine à noter' : 'Passer' }} <x-ui.icon name="arrow-right" variant="m" class="size-4" /></a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.portal>
