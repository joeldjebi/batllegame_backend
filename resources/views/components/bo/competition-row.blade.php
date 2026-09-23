@props(['competition', 'showOrganizer' => true])

@php
    $max = $competition->max_participants;
    $count = $competition->participants_count ?? 0;
@endphp

<a href="{{ route('organizers.competitions.show', [$competition->organizer, $competition]) }}"
    class="group flex items-center gap-4 rounded-xl p-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-50 to-fuchsia-50 text-brand-600 ring-1 ring-brand-100 dark:from-brand-500/10 dark:to-fuchsia-500/10 dark:text-brand-300 dark:ring-brand-400/20">
        <x-ui.icon :name="$competition->discipline->icon()" class="size-5" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2">
            <span class="truncate text-sm font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $competition->name }}</span>
            <x-ui.badge :value="$competition->status" />
        </span>
        <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400">
            @if ($showOrganizer)<span>{{ $competition->organizer->name }}</span>@endif
            <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-3.5" />{{ $competition->mode->label() }}</span>
            @if ($competition->registration_ends_at)
                <span class="inline-flex items-center gap-1"><x-ui.icon name="calendar" variant="m" class="size-3.5" />{{ $competition->registration_ends_at->translatedFormat('d M Y') }}</span>
            @endif
        </span>
    </span>
    <span class="hidden w-32 shrink-0 sm:block">
        <span class="flex justify-between text-xs"><span class="text-slate-500 dark:text-slate-400">Inscrits</span><span class="font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ $count }}{{ $max ? ' / '.$max : '' }}</span></span>
        <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
            <span class="block h-full rounded-full bg-brand-gradient" style="width: {{ $max ? min(100, round($count / $max * 100)) : min(100, $count * 5) }}%"></span>
        </span>
    </span>
    <x-ui.icon name="chevron-right" variant="m" class="size-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
</a>
