@props(['value' => null, 'tone' => null, 'dot' => true, 'icon' => null])

@php
    $isEnum = $value instanceof \App\Enums\Contracts\HasBadge;
    $tone ??= $isEnum ? $value->tone() : 'gray';

    $tones = [
        'gray' => ['bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20', 'bg-slate-400'],
        'blue' => ['bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20', 'bg-sky-500'],
        'green' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20', 'bg-emerald-500'],
        'amber' => ['bg-amber-50 text-amber-800 ring-amber-600/25 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20', 'bg-amber-500'],
        'red' => ['bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20', 'bg-rose-500'],
        'violet' => ['bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-400/10 dark:text-brand-300 dark:ring-brand-400/25', 'bg-brand-500'],
        'fuchsia' => ['bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20 dark:bg-fuchsia-400/10 dark:text-fuchsia-300 dark:ring-fuchsia-400/20', 'bg-fuchsia-500 animate-pulse'],
    ];

    [$classes, $dotClass] = $tones[$tone] ?? $tones['gray'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap', $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" variant="m" class="size-3.5" />
    @elseif ($dot)
        <span @class(['size-1.5 rounded-full', $dotClass])></span>
    @endif
    {{ $isEnum ? $value->label() : $slot }}
</span>
