@props(['title', 'description' => null, 'breadcrumbs' => []])

<div {{ $attributes->class('mb-8') }}>
    @if ($breadcrumbs)
        <nav aria-label="Fil d'Ariane" class="mb-3 flex flex-wrap items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
            @foreach ($breadcrumbs as $label => $url)
                @if (! $loop->first)<x-ui.icon name="chevron-right" variant="m" class="size-4 text-slate-300 dark:text-slate-600" />@endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="transition hover:text-brand-600 dark:hover:text-brand-300">{{ $label }}</a>
                @else
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            {{ $leading ?? '' }}
            <div class="min-w-0">
                <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ $title }}</h1>
                @if ($description)
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</div>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
