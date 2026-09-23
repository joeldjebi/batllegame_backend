@props(['title' => null, 'description' => null, 'icon' => null, 'padding' => true])

<section {{ $attributes->class('rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900/60 dark:ring-white/10') }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/5">
            <div class="flex min-w-0 items-start gap-3">
                @if ($icon)
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                        <x-ui.icon :name="$icon" class="size-5" />
                    </span>
                @endif
                <div class="min-w-0">
                    @if ($title)<h2 class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>@endif
                    @if ($description)<p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
                </div>
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="rounded-b-2xl border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-white/5 dark:bg-white/[0.02]">{{ $footer }}</footer>
    @endisset
</section>
