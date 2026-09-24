@props(['match', 'phase', 'organizer', 'competition', 'canRun' => false, 'onsite' => false])

{{-- A group of a ranking round: every artist performs, the jury and the public rank them,
     the top qualifies once the organizer publishes the phase results. --}}
@php
    use App\Enums\MatchStatus;

    $qualifiers = $phase->qualifiers_per_group ?? 1;
    $closed = $match->status === MatchStatus::Closed;
    $slots = $match->slots->whereNotNull('participant_id')
        ->sortBy(fn ($slot) => [$slot->is_forfeit ? 1 : 0, $slot->rank ?? PHP_INT_MAX, $slot->slot])->values();
    $showScores = $closed || $competition->settings->showLiveResults;
    $fmt = fn (?float $score) => $score === null ? '—' : rtrim(rtrim(number_format($score, 1, ',', ''), '0'), ',');
@endphp

<div @class([
    'overflow-hidden rounded-xl bg-white shadow-soft ring-1 dark:bg-slate-900',
    'ring-fuchsia-400/60 dark:ring-fuchsia-500/40' => $match->status === MatchStatus::Voting,
    'ring-slate-900/5 dark:ring-white/10' => $match->status !== MatchStatus::Voting,
])>
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2.5 dark:border-white/5 dark:bg-white/[0.03]">
        <div class="min-w-0">
            <h4 class="font-display text-sm font-semibold text-slate-900 dark:text-white">{{ $match->group?->name }}</h4>
            <p class="text-[11px] text-slate-500">{{ $slots->count() }} artiste(s) · {{ $qualifiers }} qualifié(s)</p>
        </div>
        <x-ui.badge :value="$match->status" />
    </div>

    <ol class="divide-y divide-slate-100 dark:divide-white/5">
        @foreach ($slots as $slot)
            @php
                $qualified = $closed && ! $slot->is_forfeit && $slot->rank !== null && $slot->rank <= $qualifiers;
            @endphp
            <li @class([
                'flex items-center gap-3 px-4 py-2.5 text-sm',
                'bg-emerald-50/60 dark:bg-emerald-500/5' => $qualified,
                'border-b-2 border-dashed !border-b-emerald-300 dark:!border-b-emerald-500/40' => $closed && $slot->rank === $qualifiers && ! $loop->last,
            ])>
                <span @class([
                    'grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums',
                    'bg-emerald-500 text-white' => $qualified,
                    'bg-slate-100 text-slate-500 dark:bg-white/10' => ! $qualified,
                ])>{{ $closed && $slot->rank ? $slot->rank : '·' }}</span>
                <x-ui.avatar :name="$slot->participant->stage_name" :src="$slot->participant->user?->avatarUrl()" size="xs" />
                <span @class(['min-w-0 flex-1 truncate font-medium', 'text-slate-400 line-through' => $slot->is_forfeit, 'text-slate-800 dark:text-slate-100' => ! $slot->is_forfeit])>{{ $slot->participant->stage_name }}</span>
                @if ($slot->is_forfeit)
                    <x-ui.badge tone="red" :dot="false">Forfait</x-ui.badge>
                @elseif ($showScores && $slot->final_score !== null)
                    @if ($phase->rules->usesJury() && $phase->rules->usesPublic())
                        <span class="hidden text-[11px] text-slate-400 tabular-nums sm:inline" title="Jury · Public">{{ $fmt($slot->jury_score) }} · {{ $fmt($slot->public_score) }}</span>
                    @endif
                    <span @class(['w-10 text-right font-display font-bold tabular-nums', 'text-emerald-600 dark:text-emerald-400' => $qualified, 'text-slate-700 dark:text-slate-200' => ! $qualified])>{{ $fmt($slot->final_score) }}</span>
                @endif
            </li>
        @endforeach
    </ol>

    @if ($match->isDeliberating())
        <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-brand-50 px-4 py-2 text-xs text-brand-800 dark:border-white/5 dark:bg-brand-500/10 dark:text-brand-200">
            <span class="inline-flex items-center gap-1 font-semibold"><x-ui.icon name="scale" variant="m" class="size-4" /> Délibération du jury</span>
            <span class="tabular-nums">jusqu'à {{ $match->deliberation_ends_at->translatedFormat('d M, H:i') }}</span>
        </div>
    @elseif ($match->isVotingOpen() && $match->voting_closes_at)
        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-white/5">Vote jusqu'au {{ $match->voting_closes_at->translatedFormat('d M, H:i') }}</p>
    @endif

    @if ($match->vote_code && $match->isVotingOpen())
        <div class="flex items-center justify-between border-t border-slate-100 bg-fuchsia-50 px-4 py-2 dark:border-white/5 dark:bg-fuchsia-500/10">
            <span class="text-[11px] font-semibold tracking-wide text-fuchsia-700 uppercase dark:text-fuchsia-300">Code de salle</span>
            <span class="font-display text-lg font-bold tracking-[0.3em] text-fuchsia-700 tabular-nums dark:text-fuchsia-200">{{ $match->vote_code }}</span>
        </div>
    @endif

    @if ($canRun && in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Voting], true))
        <div class="flex flex-wrap gap-2 border-t border-slate-100 p-2.5 dark:border-white/5">
            @if ($match->status === MatchStatus::Scheduled && $onsite)
                <form method="POST" action="{{ route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]) }}" class="flex flex-1 flex-wrap gap-1.5">
                    @csrf
                    <select name="duration" title="Durée du vote" class="rounded-lg border-0 bg-slate-50 py-1 pr-7 pl-2 text-xs ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                        <option value="">Sans limite</option>
                        @foreach ([5, 10, 15, 30] as $minutes)<option value="{{ $minutes }}">{{ $minutes }} min</option>@endforeach
                    </select>
                    <x-ui.button type="submit" size="xs" variant="soft" icon="play" class="flex-1">Ouvrir le vote de la poule</x-ui.button>
                </form>
            @elseif ($match->status === MatchStatus::Voting)
                <x-ui.confirm :action="route('organizers.competitions.matches.close', [$organizer, $competition, $match])" :danger="false" icon="flag"
                    title="Clôturer la poule ?" message="Le vote s'arrête et le classement est calculé (notes du jury et votes du public). Il reste privé jusqu'à la publication des résultats de la phase." confirm="Clôturer">
                    <x-ui.button size="xs" icon="flag" class="w-full">Clôturer la poule</x-ui.button>
                </x-ui.confirm>
            @endif
        </div>
    @endif
</div>
