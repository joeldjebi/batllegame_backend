@props(['name', 'title' => null, 'description' => null, 'icon' => null, 'maxWidth' => 'lg', 'show' => false, 'danger' => false])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

{{-- Open with $dispatch('open-modal', 'name'); reopens itself on validation errors when show is true. --}}
<div x-data="{ open: @js((bool) $show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm" x-on:click="open = false"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div x-show="open" x-trap.inert.noscroll="open"
            x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 sm:scale-95"
            @class(['relative w-full overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10', $widths[$maxWidth] ?? $widths['lg']])>
            @if ($title)
                <div class="flex items-start gap-4 px-6 pt-6">
                    @if ($icon)
                        <span @class([
                            'grid size-11 shrink-0 place-items-center rounded-xl',
                            'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300' => $danger,
                            'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300' => ! $danger,
                        ])><x-ui.icon :name="$icon" class="size-5" /></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                        @if ($description)<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
                    </div>
                    <button type="button" x-on:click="open = false" class="-mt-1 -mr-2 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-white/10">
                        <span class="sr-only">Fermer</span><x-ui.icon name="x-mark" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="px-6 py-5">{{ $slot }}</div>
        </div>
    </div>
</div>
