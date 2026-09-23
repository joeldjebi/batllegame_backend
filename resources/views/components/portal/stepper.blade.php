@props(['steps'])

{{-- Journey of a participation; scrolls horizontally on small screens. --}}
<ol {{ $attributes->class('-mx-1 flex snap-x gap-1 overflow-x-auto px-1 pb-1 [scrollbar-width:none]') }}>
    @foreach ($steps as $index => [$label, $state])
        <li class="flex min-w-max shrink-0 snap-start items-center gap-2">
            <span @class([
                'grid size-7 place-items-center rounded-full text-xs font-bold',
                'bg-emerald-500 text-white' => $state === 'done',
                'bg-brand-600 text-white ring-4 ring-brand-100 dark:ring-brand-500/20' => $state === 'current',
                'bg-rose-500 text-white' => $state === 'failed',
                'bg-slate-100 text-slate-400 dark:bg-white/10' => $state === 'todo',
            ])>
                @if ($state === 'done')<x-ui.icon name="check" variant="m" class="size-4" />@elseif ($state === 'failed')<x-ui.icon name="x-mark" variant="m" class="size-4" />@else{{ $index + 1 }}@endif
            </span>
            <span @class(['text-xs font-semibold', 'text-slate-900 dark:text-white' => in_array($state, ['current', 'done'], true), 'text-rose-600' => $state === 'failed', 'text-slate-400' => $state === 'todo'])>{{ $label }}</span>
            @unless ($loop->last)<span @class(['mx-1 h-0.5 w-6 rounded-full', 'bg-emerald-400' => $state === 'done', 'bg-slate-200 dark:bg-white/10' => $state !== 'done'])></span>@endunless
        </li>
    @endforeach
</ol>
