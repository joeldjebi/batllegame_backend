@props(['competition'])

{{-- Schedule and regulations written by the organizer (public page, anchor #reglement). --}}
@php
    $steps = $competition->scheduleList();
    $timezone = $competition->settings->timezone;
@endphp

@if ($steps !== [] || filled($competition->regulations))
    <section id="reglement" {{ $attributes->class('mb-10 grid scroll-mt-24 gap-4 lg:grid-cols-3') }}>
        @if ($steps !== [])
            <div @class(['rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-white/5 dark:ring-white/10', 'lg:col-span-3' => blank($competition->regulations)])>
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900 dark:text-white"><x-ui.icon name="calendar-days" class="size-5 text-brand-600" /> Déroulé</h2>
                <ol class="mt-4">
                    @foreach ($steps as $step)
                        @php
                            $date = $step['date'] ? \Illuminate\Support\Carbon::parse($step['date'], $timezone) : null;
                            $past = $date?->isPast();
                        @endphp
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)<span class="absolute top-7 left-3.5 h-[calc(100%-1.75rem)] w-px bg-slate-200 dark:bg-white/10"></span>@endunless
                            <span @class([
                                'relative grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold',
                                'bg-emerald-500 text-white' => $past,
                                'bg-brand-600 text-white' => ! $past,
                            ])>@if ($past)<x-ui.icon name="check" variant="m" class="size-4" />@else{{ $loop->iteration }}@endif</span>
                            <div class="min-w-0 pt-0.5">
                                <p @class(['text-sm font-semibold', 'text-slate-500' => $past, 'text-slate-900 dark:text-white' => ! $past])>{{ $step['title'] }}</p>
                                @if ($date)<p class="text-xs font-medium text-brand-700 tabular-nums dark:text-brand-300">{{ $date->translatedFormat('l d F · H:i') }}</p>@endif
                                @if ($step['details'])<p class="mt-0.5 text-xs text-slate-500">{{ $step['details'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if (filled($competition->regulations))
            <div x-data="{ open: false }" @class(['rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-white/5 dark:ring-white/10', 'lg:col-span-2' => $steps !== [], 'lg:col-span-3' => $steps === []])>
                <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-3 text-left">
                    <span class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900 dark:text-white"><x-ui.icon name="scale" class="size-5 text-brand-600" /> Règlement</span>
                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 dark:text-brand-300">
                        <span x-text="open ? 'Réduire' : 'Lire le règlement'">Lire le règlement</span>
                        <x-ui.icon name="chevron-down" variant="m" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                    </span>
                </button>
                <div x-show="open" x-collapse x-cloak class="mt-4 border-t border-slate-100 pt-4 dark:border-white/5">
                    <x-ui.rich-text :html="$competition->regulations" />
                </div>
            </div>
        @endif
    </section>
@endif
