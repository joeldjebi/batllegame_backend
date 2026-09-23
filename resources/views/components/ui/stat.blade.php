@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'brand', 'progress' => null])

@php
    $tones = [
        'brand' => 'bg-brand-600 shadow-brand-600/25',
        'green' => 'bg-emerald-500 shadow-emerald-500/25',
        'amber' => 'bg-amber-500 shadow-amber-500/25',
        'blue' => 'bg-sky-500 shadow-sky-500/25',
        'red' => 'bg-rose-500 shadow-rose-500/25',
    ];
@endphp

<div {{ $attributes->class('group relative overflow-hidden rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-900/60 dark:ring-white/10') }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white">{{ $value }}</p>
        </div>
        @if ($icon)
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl text-white shadow-lg', $tones[$tone] ?? $tones['brand']])>
                <x-ui.icon :name="$icon" class="size-5" />
            </span>
        @endif
    </div>
    @if ($progress !== null)
        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
            <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ max(0, min(100, $progress)) }}%"></div>
        </div>
    @endif
    @if ($hint)
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>
