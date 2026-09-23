@props(['name', 'src' => null, 'size' => 'md', 'square' => false])

@php
    $sizes = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg', 'xl' => 'size-20 text-2xl'];
    $gradients = [
        'from-brand-500 to-fuchsia-500', 'from-sky-500 to-indigo-500', 'from-emerald-500 to-teal-500',
        'from-amber-500 to-orange-500', 'from-rose-500 to-pink-500', 'from-cyan-500 to-blue-500',
    ];
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $gradient = $gradients[crc32($name) % count($gradients)];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class([$sizes[$size], $square ? 'rounded-xl' : 'rounded-full', 'shrink-0 object-cover ring-2 ring-white dark:ring-slate-900']) }}>
@else
    <span {{ $attributes->class([$sizes[$size], $square ? 'rounded-xl' : 'rounded-full', 'grid shrink-0 place-items-center bg-gradient-to-br font-semibold text-white ring-2 ring-white dark:ring-slate-900', $gradient]) }}>{{ $initials ?: '?' }}</span>
@endif
