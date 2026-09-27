<x-layouts.portal :title="'Notation · '.$competition->name">
    <x-app.title :title="$match->title()" :back="route('jury.competitions.show', $competition)" :back-label="$competition->name"
        :subtitle="collect([$match->stage?->name, $match->phase->effectiveMode()->label(), $match->voting_closes_at ? 'Fin du vote : '.$match->voting_closes_at->translatedFormat('d M, H:i') : null, $match->deliberation_ends_at ? 'Délibération jusqu\'au '.$match->deliberation_ends_at->translatedFormat('d M, H:i') : null])->filter()->implode(' · ')">
        <x-slot:meta><x-app.tag :value="$match->status" dot /></x-slot:meta>
    </x-app.title>

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
            <div class="rounded-2xl bg-white p-5 dark:bg-slate-900/70">
                <div class="mb-4 flex items-center gap-3">
                    <x-ui.avatar :name="$participant->stage_name" :src="$participant->user?->avatarUrl()" />
                    <p class="min-w-0 flex-1 truncate font-display text-lg font-bold">{{ $participant->stage_name }}</p>
                    @if ($mine->isNotEmpty())<x-app.tag tone="green" icon="check">Noté</x-app.tag>@else<x-app.tag tone="amber" dot>À noter</x-app.tag>@endif
                </div>

                @forelse ($media->get($participant->id, collect()) as $performance)
                    <x-app.player :performance="$performance" :title="$participant->stage_name" class="mb-4" />
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
            </div>
        @endforeach
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::jury($competition->id), \App\Realtime\Channel::user(auth('jury')->id())]" />
</x-layouts.portal>
