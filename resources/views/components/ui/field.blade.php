@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $for }}" class="block text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($error)
        <p class="flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
            <x-ui.icon name="exclamation-circle" variant="m" class="size-4" /> {{ $error }}
        </p>
    @elseif ($hint)
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>
