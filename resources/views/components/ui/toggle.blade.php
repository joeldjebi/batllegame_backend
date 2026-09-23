@props(['name', 'label', 'description' => null, 'checked' => false])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $on = (bool) old($key, $checked);
@endphp

<label x-data="{ on: @js($on) }" {{ $attributes->class('flex cursor-pointer items-start justify-between gap-4 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]') }}>
    <span>
        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ $label }}</span>
        @if ($description)<span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $description }}</span>@endif
    </span>
    <input type="hidden" name="{{ $name }}" :value="on ? 1 : 0">
    <button type="button" role="switch" :aria-checked="on" x-on:click="on = ! on"
        :class="on ? 'bg-brand-600' : 'bg-slate-200 dark:bg-white/10'"
        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
        <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block size-5 rounded-full bg-white shadow ring-0 transition-transform"></span>
    </button>
</label>
