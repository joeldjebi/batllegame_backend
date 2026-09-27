@props(['href' => null, 'icon' => null, 'tone' => 'gray', 'title', 'subtitle' => null, 'value' => null, 'valueTone' => null, 'chevron' => true, 'unread' => false])

{{-- One row of a section: icon tile (or a leading slot), title, subtitle, value, chevron. --}}
@php
    $tiles = [
        'gray' => 'bg-slate-400 dark:bg-slate-600', 'brand' => 'bg-brand-600', 'red' => 'bg-rose-500',
        'green' => 'bg-emerald-500', 'blue' => 'bg-sky-500', 'amber' => 'bg-amber-500',
    ];
    $values = ['green' => 'text-emerald-600 dark:text-emerald-400', 'amber' => 'text-amber-600 dark:text-amber-400', 'red' => 'text-rose-600 dark:text-rose-400', 'brand' => 'text-brand-600 dark:text-brand-300'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['flex min-h-14 items-center gap-4 px-4 py-3', 'transition hover:bg-slate-50 dark:hover:bg-white/[0.03]' => $href]) }}>
    @isset($leading)
        {{ $leading }}
    @elseif ($icon)
        <span class="grid size-8 shrink-0 place-items-center rounded-lg text-white {{ $tiles[$tone] ?? $tiles['gray'] }}"><x-ui.icon :name="$icon" variant="s" class="size-[18px]" /></span>
    @endisset
    <span class="min-w-0 flex-1">
        <span @class(['block truncate text-[15px] text-slate-900 dark:text-white', 'font-semibold' => $unread])>{{ $title }}</span>
        @if ($subtitle)<span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</span>@endif
        @isset($detail)<span class="mt-1 block">{{ $detail }}</span>@endisset
    </span>
    @if ($value)<span class="shrink-0 text-sm {{ $values[$valueTone] ?? 'text-slate-500 dark:text-slate-400' }}">{{ $value }}</span>@endif
    {{ $trailing ?? '' }}
    @if ($unread)<span class="size-2 shrink-0 rounded-full bg-brand-600"></span>@endif
    @if ($href && $chevron)<x-ui.icon name="chevron-right" variant="m" class="size-4 shrink-0 text-slate-300 dark:text-slate-600" />@endif
</{{ $tag }}>
