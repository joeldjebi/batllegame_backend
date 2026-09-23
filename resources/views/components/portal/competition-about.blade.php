@props(['competition'])

@php($prizes = $competition->prizeList())

{{-- Public presentation of a competition: organizer rich text + ordered rewards. --}}
@if (filled($competition->description) || $prizes !== [])
    <section {{ $attributes->class('mb-10 grid gap-4 lg:grid-cols-3') }}>
        @if (filled($competition->description))
            <div x-data="{ open: false, long: false }" x-init="long = $refs.body.scrollHeight > 260"
                @class(['rounded-3xl bg-white p-5 shadow-soft ring-1 ring-slate-900/5 sm:p-6 dark:bg-white/5 dark:ring-white/10', 'lg:col-span-2' => $prizes !== [], 'lg:col-span-3' => $prizes === []])>
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900 dark:text-white"><x-ui.icon name="document-text" class="size-5 text-brand-600" /> À propos</h2>
                <div class="relative mt-3 overflow-hidden transition-[max-height] duration-300" x-ref="body" :style="long && ! open ? 'max-height: 260px' : ''">
                    <x-ui.rich-text :html="$competition->description" />
                    <div x-show="long && ! open" class="absolute inset-x-0 bottom-0 h-16 bg-white/85 dark:bg-slate-900/85"></div>
                </div>
                <button type="button" x-show="long" x-cloak x-on:click="open = ! open" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 dark:text-brand-300">
                    <span x-text="open ? 'Réduire' : 'Lire la suite'"></span>
                    <x-ui.icon name="chevron-down" variant="m" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                </button>
            </div>
        @endif

        @if ($prizes !== [])
            <div @class(['rounded-3xl bg-slate-950 p-5 text-white sm:p-6', 'lg:col-span-3' => blank($competition->description)])>
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold"><x-ui.icon name="trophy" class="size-5 text-amber-400" /> À gagner</h2>
                <ol class="mt-4 space-y-2.5">
                    @foreach ($prizes as $i => $prize)
                        <li class="flex items-start gap-3 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
                            <span @class([
                                'grid size-9 shrink-0 place-items-center rounded-xl font-display text-sm font-extrabold',
                                'bg-amber-400 text-slate-950' => $i === 0,
                                'bg-slate-300 text-slate-950' => $i === 1,
                                'bg-orange-400 text-slate-950' => $i === 2,
                                'bg-white/10 text-white' => $i > 2,
                            ])>{{ $i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold tracking-wide text-white/60 uppercase">{{ $prize['rank'] }}</p>
                                <p class="font-semibold break-words">{{ $prize['reward'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
@endif
