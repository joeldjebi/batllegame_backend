@props(['group', 'qualifiers' => 0])

<div class="overflow-hidden rounded-xl ring-1 ring-slate-900/5 dark:ring-white/10">
    <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5 dark:bg-white/[0.03]">
        <h4 class="font-display text-sm font-semibold text-slate-900 dark:text-white">{{ $group->name }}</h4>
        @if ($qualifiers)<span class="text-[11px] text-slate-500">{{ $qualifiers }} qualifié(s)</span>@endif
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] tracking-wider text-slate-400 uppercase">
                <th class="py-2 pl-4 text-left font-semibold">#</th>
                <th class="py-2 text-left font-semibold">Participant</th>
                <th class="py-2 text-center font-semibold" title="Joués">J</th>
                <th class="py-2 text-center font-semibold" title="Victoires / Nuls / Défaites">V-N-D</th>
                <th class="py-2 text-center font-semibold" title="Différence de score">+/-</th>
                <th class="py-2 pr-4 text-right font-semibold">Pts</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach ($group->standings as $standing)
                @php($qualified = $standing->rank !== null && $standing->rank <= $qualifiers)
                <tr @class(['bg-emerald-50/50 dark:bg-emerald-500/5' => $qualified])>
                    <td class="py-2.5 pl-4">
                        <span @class([
                            'grid size-6 place-items-center rounded-full text-xs font-bold tabular-nums',
                            'bg-emerald-500 text-white' => $qualified,
                            'bg-slate-100 text-slate-500 dark:bg-white/10' => ! $qualified,
                        ])>{{ $standing->rank ?? '–' }}</span>
                    </td>
                    <td class="py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ $standing->participant->stage_name }}</td>
                    <td class="py-2.5 text-center text-slate-500 tabular-nums">{{ $standing->wins + $standing->draws + $standing->losses }}</td>
                    <td class="py-2.5 text-center text-slate-500 tabular-nums">{{ $standing->wins }}-{{ $standing->draws }}-{{ $standing->losses }}</td>
                    <td @class(['py-2.5 text-center tabular-nums', 'text-emerald-600' => $standing->score_diff > 0, 'text-rose-500' => $standing->score_diff < 0, 'text-slate-400' => $standing->score_diff == 0])>{{ $standing->score_diff > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($standing->score_diff, 1, ',', ''), '0'), ',') ?: '0' }}</td>
                    <td class="py-2.5 pr-4 text-right font-display font-bold text-slate-900 tabular-nums dark:text-white">{{ $standing->points }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
