@props(['value' => null, 'tone' => null, 'dot' => false, 'icon' => null])

{{-- Small tag (status, discipline, price): tinted with a color, grey otherwise. A status enum gives its label and color. --}}
@php
    $isEnum = $value instanceof \App\Enums\Contracts\HasBadge;
    $tone ??= $isEnum ? (['violet' => 'brand', 'fuchsia' => 'red'][$value->tone()] ?? $value->tone()) : 'gray';
    $tones = [
        'gray' => 'bg-slate-500/10 text-slate-600 dark:text-slate-300', 'brand' => 'bg-brand-500/10 text-brand-700 dark:text-brand-300',
        'red' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400', 'green' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        'amber' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400', 'blue' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    ];
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold '.($tones[$tone] ?? $tones['gray'])) }}>
    @if ($dot)<span class="size-1.5 rounded-full bg-current"></span>@elseif ($icon)<x-ui.icon :name="$icon" variant="m" class="size-3.5" />@endif
    {{ $isEnum ? $value->label() : $slot }}
</span>
