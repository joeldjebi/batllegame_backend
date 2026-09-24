<x-layouts.portal :title="'Notation · '.$competition->name">
    <x-ui.page-header :title="$match->title()"
        :breadcrumbs="['Mes compétitions' => route('jury.dashboard'), $competition->name => route('jury.competitions.show', $competition), $match->stage?->name ?? 'Match' => null]">
        <x-slot:description>
            <x-ui.badge :value="$match->status" />
            <span>{{ $match->phase->effectiveMode()->label() }}</span>
            @if ($match->voting_closes_at)<span>Fin du vote : {{ $match->voting_closes_at->translatedFormat('d M, H:i') }}</span>@endif
            @if ($match->deliberation_ends_at)<span>Délibération jusqu'au {{ $match->deliberation_ends_at->translatedFormat('d M, H:i') }}</span>@endif
        </x-slot:description>
    </x-ui.page-header>

    @if ($canScore && $match->juryDeadline())
        <div @class(['mb-6 flex flex-wrap items-center gap-2 rounded-2xl p-4 text-sm ring-1', 'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200' => $match->isDeliberating(), 'bg-brand-50 text-brand-800 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-200' => ! $match->isDeliberating()])
            x-data="countdown('{{ $match->juryDeadline()->toIso8601String() }}')">
            <x-ui.icon name="scale" class="size-5 shrink-0" />
            {{ $match->isDeliberating() ? 'Vote du public clos : délibération du jury, clôture dans' : 'Vos notes sont modifiables jusqu\'à la clôture, dans' }}
            <strong class="font-display tabular-nums" x-text="label">{{ $match->juryDeadline()->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</strong>
        </div>
    @endif

    @unless ($canScore)
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-slate-100 p-4 text-sm text-slate-600 dark:bg-white/5 dark:text-slate-300">
            <x-ui.icon name="lock-closed" class="size-5 shrink-0" /> {{ $match->status === \App\Enums\MatchStatus::Voting && $match->juryDeadline()?->isPast() ? 'La délibération est close' : 'La notation de ce match n\'est pas ouverte' }} : vos notes sont affichées en lecture seule.
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($match->slots->filter(fn ($slot) => $slot->participant && ! $slot->is_forfeit) as $slot)
            @php($participant = $slot->participant)
            @php($mine = $myScores->get($participant->id, collect())->keyBy('criterion_id'))
            <x-ui.card :title="$participant->stage_name" icon="microphone">
                <x-slot:actions>
                    @if ($mine->isNotEmpty())<x-ui.badge tone="green">Noté</x-ui.badge>@else<x-ui.badge tone="amber">À noter</x-ui.badge>@endif
                </x-slot:actions>

                @forelse ($media->get($participant->id, collect()) as $performance)
                    <x-bo.media-player :performance="$performance" class="mb-4" />
                @empty
                    <div class="mb-4 rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-white/10">
                        {{ $match->phase->effectiveMode() === \App\Enums\CompetitionMode::OnSite ? 'Prestation sur scène : notez en direct.' : 'Aucun média publié.' }}
                    </div>
                @endforelse

                <form method="POST" action="{{ route('jury.competitions.matches.scores.store', [$competition, $match]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                    <fieldset @disabled(! $canScore) class="space-y-4">
                        @foreach ($criteria as $i => $criterion)
                            @php($current = $mine->get($criterion->id)?->score)
                            <div x-data="{ value: {{ $current ?? 0 }} }">
                                <input type="hidden" name="scores[{{ $i }}][criterion_id]" value="{{ $criterion->id }}">
                                <div class="flex items-center justify-between text-sm">
                                    <label class="font-medium">{{ $criterion->name }}</label>
                                    <span class="font-display font-bold tabular-nums"><span x-text="value"></span> / {{ $criterion->max_points }}</span>
                                </div>
                                <input type="range" name="scores[{{ $i }}][score]" min="0" max="{{ $criterion->max_points }}" step="0.5" x-model.number="value" class="mt-2 w-full accent-brand-600">
                            </div>
                        @endforeach
                        <x-ui.textarea name="scores[0][comment]" label="Commentaire (optionnel)" rows="2" :value="$mine->first()?->comment" />
                        @if ($canScore)
                            <x-ui.button type="submit" class="w-full" icon="check">{{ $mine->isNotEmpty() ? 'Mettre à jour mes notes' : 'Enregistrer mes notes' }}</x-ui.button>
                        @endif
                    </fieldset>
                </form>
            </x-ui.card>
        @endforeach
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::jury($competition->id), \App\Realtime\Channel::user(auth('jury')->id())]" />
</x-layouts.portal>
