@props(['align' => 'right', 'width' => 'w-56'])

{{-- The menu is teleported to <body> and anchored to its trigger (@alpinejs/anchor): never clipped by
     an overflow-hidden card or table, flipped above the trigger when there is no room below. --}}
<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" {{ $attributes->class('relative inline-block text-left') }}>
    <div x-ref="trigger" x-on:click="open = ! open">{{ $trigger }}</div>

    <template x-teleport="body">
        <div x-show="open" x-cloak
            x-anchor.{{ $align === 'left' ? 'bottom-start' : 'bottom-end' }}.offset.8="$refs.trigger"
            x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false"
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            @class(['z-[55] rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10', $width])
            x-on:click="open = false">
            {{ $slot }}
        </div>
    </template>
</div>
