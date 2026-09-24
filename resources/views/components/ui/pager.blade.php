@props(['count' => 0, 'perPage' => 15])

{{-- Controls of Alpine.data('pager'): only shown when the filtered list has more than one page.
     One button per possible page (count rows at most), shown while it exists. --}}
<div x-show="pages > 1" x-cloak {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm dark:border-white/5') }}>
    <p class="text-slate-500 tabular-nums"><span x-text="from"></span>–<span x-text="to"></span> sur <span x-text="total"></span></p>
    <nav class="flex items-center gap-1" aria-label="Pagination">
        <button type="button" x-on:click="go(page - 1)" x-bind:disabled="page === 1" class="grid size-8 place-items-center rounded-lg text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 disabled:opacity-40 dark:ring-white/10 dark:hover:bg-white/5" aria-label="Page précédente">
            <x-ui.icon name="chevron-left" variant="m" class="size-4" />
        </button>
        @for ($number = 1; $number <= max(1, (int) ceil($count / $perPage)); $number++)
            <button type="button" x-on:click="go({{ $number }})" x-show="{{ $number }} <= pages && (pages <= 7 || {{ $number }} === 1 || {{ $number }} === pages || Math.abs({{ $number }} - page) <= 1)"
                x-bind:class="{{ $number }} === page ? 'bg-slate-900 text-white ring-slate-900 dark:bg-white dark:text-slate-900 dark:ring-white' : 'text-slate-600 ring-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white/5'"
                class="h-8 min-w-8 rounded-lg px-2 text-sm font-semibold tabular-nums ring-1 transition">{{ $number }}</button>
        @endfor
        <button type="button" x-on:click="go(page + 1)" x-bind:disabled="page === pages" class="grid size-8 place-items-center rounded-lg text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 disabled:opacity-40 dark:ring-white/10 dark:hover:bg-white/5" aria-label="Page suivante">
            <x-ui.icon name="chevron-right" variant="m" class="size-4" />
        </button>
    </nav>
</div>
