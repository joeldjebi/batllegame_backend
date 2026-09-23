@props(['href', 'icon' => null, 'active' => false, 'count' => null])

<a href="{{ $href }}" @class([
    'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
    'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' => $active,
    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $active,
]) @if ($active) aria-current="page" @endif>
    @if ($icon)
        <x-ui.icon :name="$icon" @class(['size-5', 'text-brand-600 dark:text-brand-300' => $active, 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200' => ! $active]) />
    @endif
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($count !== null)
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 tabular-nums dark:bg-white/10 dark:text-slate-300">{{ $count }}</span>
    @endif
</a>
