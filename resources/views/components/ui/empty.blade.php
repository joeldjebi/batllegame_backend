@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center dark:border-white/10') }}>
    <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 font-display text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
