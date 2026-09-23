@props(['name', 'title', 'description' => null, 'icon' => null, 'show' => false])

{{-- Right-hand panel for longer forms. Open with $dispatch('open-modal', 'name'). --}}
<div x-data="{ open: @js((bool) $show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" x-on:click="open = false"></div>

    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
        <div x-show="open" x-trap.inert.noscroll="open"
            x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="pointer-events-auto flex h-full w-screen max-w-xl flex-col bg-white shadow-2xl dark:bg-slate-900">
            <div class="relative overflow-hidden bg-brand-600 px-6 py-6 text-white">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        @if ($icon)<span class="grid size-10 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25"><x-ui.icon :name="$icon" class="size-5" /></span>@endif
                        <div>
                            <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
                            @if ($description)<p class="mt-0.5 text-sm text-white/80">{{ $description }}</p>@endif
                        </div>
                    </div>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-white/80 transition hover:bg-white/15 hover:text-white">
                        <span class="sr-only">Fermer</span><x-ui.icon name="x-mark" class="size-5" />
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-6">{{ $slot }}</div>
        </div>
    </div>
</div>
