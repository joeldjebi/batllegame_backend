@props(['match', 'organizer', 'competition', 'canRun' => false, 'compact' => false])

@php
    use App\Enums\MatchStatus;

    $matchSlots = $match->slots->sortBy('slot')->values();
    $filled = $matchSlots->whereNotNull('participant_id')->count();
    $playable = in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Submissions], true) && $filled === 2;
    $decided = in_array($match->status, [MatchStatus::Closed, MatchStatus::Cancelled], true);
    $isWalkover = $match->status === MatchStatus::Closed && $filled === 1;
@endphp

<div {{ $attributes->class([
    'bracket-match relative w-full rounded-xl bg-white shadow-soft ring-1 transition dark:bg-slate-900',
    'ring-fuchsia-400/60 shadow-fuchsia-500/10 dark:ring-fuchsia-500/40' => $match->status === MatchStatus::Voting,
    'ring-slate-900/5 dark:ring-white/10' => $match->status !== MatchStatus::Voting,
    'opacity-60' => $match->status === MatchStatus::Cancelled,
]) }}>
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-1.5 dark:border-white/5">
        <span class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">
            {{ $match->group ? 'J'.$match->round : 'M'.$match->bracket_position }}
            @if ($isWalkover)<span class="ml-1 normal-case">· exempt</span>@endif
        </span>
        @unless ($match->status === MatchStatus::Scheduled && ! $playable)
            <x-ui.badge :value="$match->status" class="!px-1.5 !py-0 !text-[10px]" />
        @endunless
    </div>

    @foreach ($matchSlots as $entry)
        @php($isWinner = $match->winner_id && $entry->participant_id === $match->winner_id)
        <div @class([
            'flex items-center gap-2 px-3 py-2 text-sm',
            'bg-emerald-50/70 dark:bg-emerald-500/10' => $isWinner,
            'border-t border-slate-100 dark:border-white/5' => ! $loop->first,
        ])>
            @if ($entry->participant)
                <span class="grid size-5 shrink-0 place-items-center rounded-md bg-slate-100 text-[10px] font-bold text-slate-500 tabular-nums dark:bg-white/10 dark:text-slate-400">{{ $entry->participant->seed ?? '–' }}</span>
                <span @class(['min-w-0 flex-1 truncate', 'font-semibold text-slate-900 dark:text-white' => $isWinner, 'text-slate-700 dark:text-slate-300' => ! $isWinner, 'line-through decoration-slate-300 text-slate-400' => $decided && ! $isWinner && $match->winner_id])>{{ $entry->participant->stage_name }}</span>
                @if ($isWinner)<x-ui.icon name="trophy" variant="m" class="size-4 text-amber-500" />@endif
                @if ($entry->final_score !== null)
                    <span @class(['font-display text-sm font-bold tabular-nums', 'text-emerald-600 dark:text-emerald-400' => $isWinner, 'text-slate-400' => ! $isWinner])>{{ rtrim(rtrim(number_format($entry->final_score, 1, ',', ''), '0'), ',') }}</span>
                @endif
            @else
                <span class="size-5 shrink-0 rounded-md border border-dashed border-slate-200 dark:border-white/10"></span>
                <span class="flex-1 text-slate-400 italic">{{ $decided ? '—' : 'À déterminer' }}</span>
            @endif
        </div>
    @endforeach

    @if ($canRun && ($playable || $match->status === MatchStatus::Voting))
        <div class="flex gap-1.5 border-t border-slate-100 p-2 dark:border-white/5">
            @if ($playable)
                <form method="POST" action="{{ route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]) }}" class="flex-1">
                    @csrf
                    <x-ui.button type="submit" size="xs" variant="soft" icon="play" class="w-full">Ouvrir le vote</x-ui.button>
                </form>
            @else
                <x-ui.confirm :action="route('organizers.competitions.matches.close', [$organizer, $competition, $match])" :danger="false" icon="flag"
                    title="Clôturer le match ?" message="Les scores seront calculés et le vainqueur avancera automatiquement. En cas d'égalité parfaite, désignez le vainqueur." confirm="Clôturer">
                    <x-ui.button size="xs" icon="flag" class="w-full flex-1">Clôturer</x-ui.button>
                    <x-slot:fields>
                        <div class="mb-3 w-full sm:mb-0 sm:mr-auto">
                            <select name="winner_id" class="w-full rounded-xl border-0 bg-slate-50 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                                <option value="">Vainqueur calculé</option>
                                @foreach ($matchSlots as $entry)<option value="{{ $entry->participant_id }}">En cas d'égalité : {{ $entry->participant?->stage_name }}</option>@endforeach
                            </select>
                        </div>
                    </x-slot:fields>
                </x-ui.confirm>
            @endif
        </div>
    @endif
</div>
