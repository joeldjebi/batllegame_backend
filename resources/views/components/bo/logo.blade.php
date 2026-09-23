@props(['admin' => false, 'light' => false])

<span {{ $attributes->class('flex items-center gap-2.5') }}>
    <span class="relative grid size-9 place-items-center rounded-xl bg-brand-600 text-white shadow-lift">
        <x-ui.icon :name="$admin ? 'shield-check' : 'bolt'" variant="s" class="size-5" />
    </span>
    <span class="leading-tight">
        <span @class(['block font-display text-[15px] font-bold tracking-tight', 'text-white' => $light, 'text-slate-900 dark:text-white' => ! $light])>Battle Game</span>
        <span @class(['block text-[11px] font-medium tracking-wide uppercase', 'text-white/60' => $light, 'text-slate-400' => ! $light])>{{ $admin ? 'Console admin' : 'Back-office' }}</span>
    </span>
</span>
