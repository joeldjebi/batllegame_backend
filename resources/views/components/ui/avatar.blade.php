@props(['name', 'src' => null, 'size' => 'md', 'square' => false])

@php
    $sizes = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg', 'xl' => 'size-20 text-2xl'];
    $colors = ['bg-brand-600', 'bg-sky-600', 'bg-emerald-600', 'bg-amber-500', 'bg-rose-600', 'bg-cyan-600'];
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $color = $colors[crc32($name) % count($colors)];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class([$sizes[$size], $square ? 'rounded-xl' : 'rounded-full', 'shrink-0 object-cover ring-2 ring-white dark:ring-slate-900']) }}>
@else
    <span {{ $attributes->class([$sizes[$size], $square ? 'rounded-xl' : 'rounded-full', 'grid shrink-0 place-items-center font-semibold text-white ring-2 ring-white dark:ring-slate-900', $color]) }}>{{ $initials ?: '?' }}</span>
@endif
