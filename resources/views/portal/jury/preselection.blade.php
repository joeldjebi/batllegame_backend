<x-layouts.portal :title="'Présélection · '.$competition->name">
    @php($state = $preselection->state())
    @php($canScore = in_array($state, [\App\Enums\PreselectionState::Open, \App\Enums\PreselectionState::Closed], true))
    <x-ui.page-header title="Présélection" :breadcrumbs="['Mes compétitions' => route('jury.dashboard'), $competition->name => route('jury.competitions.show', $competition), 'Présélection' => null]">
        <x-slot:description>
            <x-ui.badge :value="$state" />
            <span>{{ $entries->count() }} prestation(s) · jury {{ $preselection->rules->juryWeight }} % du score</span>
        </x-slot:description>
    </x-ui.page-header>

    @unless ($canScore)
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-slate-100 p-4 text-sm text-slate-600 dark:bg-white/5 dark:text-slate-300">
            <x-ui.icon name="lock-closed" class="size-5 shrink-0" /> {{ $state === \App\Enums\PreselectionState::Published ? 'La sélection est publiée : les notes sont en lecture seule.' : 'La présélection n\'a pas encore commencé.' }}
        </div>
    @endunless

    @if ($entries->isEmpty())
        <x-ui.empty icon="film" title="Aucune prestation à noter" description="Les prestations validées par l'organisateur apparaîtront ici." />
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($entries as $entry)
                @php($mine = $myScores->get($entry->id, collect())->keyBy('criterion_id'))
                <x-ui.card :title="$entry->participant->stage_name" icon="microphone">
                    <x-slot:actions>
                        @if ($mine->isNotEmpty())<x-ui.badge tone="green">Noté</x-ui.badge>@else<x-ui.badge tone="amber">À noter</x-ui.badge>@endif
                    </x-slot:actions>
                    <x-bo.media-player :performance="$entry" class="mb-4" />
                    <form method="POST" action="{{ route('jury.competitions.preselection.scores.store', [$competition, $entry]) }}" class="space-y-4">
                        @csrf
                        <fieldset @disabled(! $canScore) class="space-y-4">
                            @foreach ($criteria as $i => $criterion)
                                <div x-data="{ value: {{ $mine->get($criterion->id)?->score ?? 0 }} }">
                                    <input type="hidden" name="scores[{{ $i }}][criterion_id]" value="{{ $criterion->id }}">
                                    <div class="flex items-center justify-between text-sm">
                                        <label class="font-medium">{{ $criterion->name }}</label>
                                        <span class="font-display font-bold tabular-nums"><span x-text="value"></span> / {{ $criterion->max_points }}</span>
                                    </div>
                                    <input type="range" name="scores[{{ $i }}][score]" min="0" max="{{ $criterion->max_points }}" step="0.5" x-model.number="value" class="mt-2 w-full accent-brand-600">
                                </div>
                            @endforeach
                            @if ($canScore)
                                <x-ui.button type="submit" class="w-full" icon="check">{{ $mine->isNotEmpty() ? 'Mettre à jour mes notes' : 'Enregistrer mes notes' }}</x-ui.button>
                            @endif
                        </fieldset>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.portal>
