@props(['title', 'back' => null, 'backLabel' => 'Retour', 'subtitle' => null])

{{-- Large page title, the mobile app way (optional back link above it, actions on the right). --}}
<div {{ $attributes->class('mb-6') }}>
    @if ($back)
        <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">
            <x-ui.icon name="chevron-left" variant="m" class="size-4" /> {{ $backLabel }}
        </a>
    @endif
    <div class="flex items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-display text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
            @isset($meta)<div class="mt-2 flex flex-wrap items-center gap-1.5">{{ $meta }}</div>@endisset
        </div>
        @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
    </div>
</div>
