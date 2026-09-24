@props(['url', 'title', 'text' => null, 'label' => 'Partager', 'icon' => false, 'size' => 'sm', 'variant' => 'secondary', 'align' => 'right'])

{{-- Share a link: native share sheet on phones (Web Share API), otherwise WhatsApp / Facebook / X / Telegram / copy. --}}
@php
    $message = trim(($text ?? $title).' '.$url);
    $links = [
        ['WhatsApp', 'https://wa.me/?text='.rawurlencode($message), 'bg-emerald-500'],
        ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url), 'bg-blue-600'],
        ['X', 'https://twitter.com/intent/tweet?text='.rawurlencode($text ?? $title).'&url='.rawurlencode($url), 'bg-slate-900'],
        ['Telegram', 'https://t.me/share/url?url='.rawurlencode($url).'&text='.rawurlencode($text ?? $title), 'bg-sky-500'],
    ];
@endphp

<div x-data="share({ url: '{{ $url }}', title: @js($title), text: @js($text ?? $title) })" {{ $attributes->class('relative inline-flex') }} x-on:keydown.escape="open = false" x-on:click.outside="open = false">
    @if ($icon)
        <button type="button" x-on:click="trigger()" title="{{ $label }}" aria-label="{{ $label }}"
            class="grid size-9 place-items-center rounded-xl text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-brand-700 active:scale-95 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/5">
            <x-ui.icon name="share" variant="m" class="size-4" />
        </button>
    @else
        <x-ui.button :size="$size" :variant="$variant" icon="share" x-on:click="trigger()">{{ $label }}</x-ui.button>
    @endif

    <div x-show="open" x-cloak x-transition
        @class(['absolute top-full z-40 mt-2 w-60 rounded-2xl bg-white p-2 shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10', 'right-0' => $align === 'right', 'left-0' => $align === 'left'])>
        <p class="px-2 pt-1 pb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Partager</p>
        @foreach ($links as [$name, $href, $color])
            <a href="{{ $href }}" target="_blank" rel="noopener" x-on:click="open = false"
                class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/10">
                <span class="grid size-7 place-items-center rounded-lg text-xs font-bold text-white {{ $color }}">{{ mb_substr($name, 0, 1) }}</span>{{ $name }}
            </a>
        @endforeach
        <button type="button" x-on:click="copy()" class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/10">
            <span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-200"><x-ui.icon name="link" variant="m" class="size-4" /></span>
            <span x-text="copied ? 'Lien copié !' : 'Copier le lien'">Copier le lien</span>
        </button>
    </div>
</div>
