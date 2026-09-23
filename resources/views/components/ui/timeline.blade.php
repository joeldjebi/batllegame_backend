@props(['items' => [], 'deadline' => null, 'deadlineLabel' => null])

{{-- Milestones of an organizer timeline. $items = [[label, Carbon|null date, 'done'|'current'|'todo', ?icon]].
     $deadline shows a live countdown to the current milestone. --}}
<div {{ $attributes->class('rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10') }}>
    <ol class="grid gap-3 sm:auto-cols-fr sm:grid-flow-col sm:gap-2">
        @foreach ($items as [$label, $date, $status, $icon])
            <li class="relative flex items-center gap-3 sm:flex-col sm:items-start sm:gap-2">
                <div class="flex items-center gap-2 sm:w-full">
                    <span @class([
                        'grid size-8 shrink-0 place-items-center rounded-full ring-4',
                        'bg-emerald-500 text-white ring-emerald-500/15' => $status === 'done',
                        'bg-brand-600 text-white ring-brand-600/20' => $status === 'current',
                        'bg-slate-100 text-slate-400 ring-transparent dark:bg-white/10' => $status === 'todo',
                    ])><x-ui.icon :name="$status === 'done' ? 'check' : ($icon ?? 'clock')" variant="m" class="size-4" /></span>
                    @unless ($loop->last)
                        <span @class(['hidden h-0.5 flex-1 rounded-full sm:block', 'bg-emerald-500' => $status === 'done', 'bg-slate-200 dark:bg-white/10' => $status !== 'done'])></span>
                    @endunless
                </div>
                <div class="min-w-0">
                    <p @class(['text-sm font-semibold', 'text-brand-700 dark:text-brand-300' => $status === 'current', 'text-slate-800 dark:text-slate-100' => $status === 'done', 'text-slate-400' => $status === 'todo'])>{{ $label }}</p>
                    <p class="text-xs text-slate-500 tabular-nums">{{ $date?->translatedFormat('d M, H:i') ?? '—' }}</p>
                </div>
            </li>
        @endforeach
    </ol>
    @if ($deadline && $deadline->isFuture())
        <p class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-sm text-slate-600 dark:border-white/10 dark:text-slate-300" x-data="countdown('{{ $deadline->toIso8601String() }}')">
            <x-ui.icon name="clock" variant="m" class="size-4 text-brand-600" /> {{ $deadlineLabel }}
            <strong class="font-display tabular-nums text-slate-900 dark:text-white" x-text="label">{{ $deadline->diffForHumans() }}</strong>
        </p>
    @endif
</div>
