@props(['align' => 'right', 'width' => 'w-56'])

<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" {{ $attributes->class('relative inline-block text-left') }}>
    <div x-on:click="open = ! open">{{ $trigger }}</div>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        @class([
            'absolute z-40 mt-2 origin-top-right rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
        ])
        x-on:click="open = false">
        {{ $slot }}
    </div>
</div>
