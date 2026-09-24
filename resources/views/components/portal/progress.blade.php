@props(['steps'])

{{-- Journey of a participation: a segmented bar + the current step on phones, the full stepper from sm. --}}
@php
    $total = count($steps);
    $index = collect($steps)->search(fn ($step) => in_array($step[1], ['current', 'failed'], true));
    $index = $index === false ? $total - 1 : $index;
    [$label, $state] = $steps[$index];
    $color = fn (string $s) => match ($s) { 'done' => 'bg-emerald-500', 'current' => 'bg-brand-600', 'failed' => 'bg-rose-500', default => 'bg-slate-200 dark:bg-white/10' };
@endphp

<div {{ $attributes }}>
    <div class="sm:hidden">
        <div class="flex items-center justify-between text-xs">
            <span @class(['font-semibold', 'text-brand-700 dark:text-brand-300' => $state === 'current', 'text-rose-600' => $state === 'failed', 'text-emerald-700 dark:text-emerald-300' => $state === 'done'])>
                {{ $state === 'failed' ? 'Arrêté à' : ($state === 'done' ? 'Terminé' : 'Étape en cours') }} · {{ $label }}
            </span>
            <span class="font-medium text-slate-400 tabular-nums">{{ $index + 1 }}/{{ $total }}</span>
        </div>
        <div class="mt-2 flex gap-1">
            @foreach ($steps as [$stepLabel, $stepState])
                <span title="{{ $stepLabel }}" class="h-1.5 flex-1 rounded-full {{ $color($stepState) }} {{ $stepState === 'current' ? 'animate-pulse' : '' }}"></span>
            @endforeach
        </div>
    </div>
    <x-portal.stepper :steps="$steps" class="hidden sm:flex" />
</div>
