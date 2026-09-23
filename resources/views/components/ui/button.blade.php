@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center font-semibold whitespace-nowrap rounded-xl transition duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98]';

    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm shadow-brand-600/25 hover:bg-brand-500 focus-visible:outline-brand-600',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-200 shadow-soft hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-slate-400 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10 dark:hover:bg-white/10',
        'soft' => 'bg-brand-50 text-brand-700 hover:bg-brand-100 focus-visible:outline-brand-600 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white shadow-sm shadow-rose-600/25 hover:bg-rose-500 focus-visible:outline-rose-600',
        'danger-soft' => 'bg-rose-50 text-rose-700 hover:bg-rose-100 focus-visible:outline-rose-600 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20',
    ];

    $sizes = [
        'xs' => 'gap-1 px-2 py-1 text-xs',
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-xs',
        'md' => 'gap-2 px-3.5 py-2 text-sm',
        'lg' => 'gap-2 px-5 py-2.5 text-sm',
    ];

    $iconSize = in_array($size, ['xs', 'sm'], true) ? 'size-4' : 'size-[1.125rem]';
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconSize" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconSize" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconSize" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconSize" />@endif
    </button>
@endif
