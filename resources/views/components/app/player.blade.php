@props(['performance', 'title' => null])

{{-- Video to watch closely, the same controls as the mobile app (see reviewPlayer in app.js).
     Audio files keep the browser player. --}}
@if ($performance->media_type === \App\Enums\MediaType::Audio)
    <div {{ $attributes->class('rounded-2xl bg-slate-950 p-4') }}>
        <audio controls preload="metadata" src="{{ $performance->mediaUrl() }}" class="w-full"></audio>
    </div>
@else
    <div x-data="reviewPlayer" {{ $attributes->class('group relative aspect-video w-full overflow-hidden rounded-2xl bg-black select-none') }}
        x-on:click="tap($event)" x-bind:class="full && 'rounded-none'">
        <video x-ref="video" playsinline preload="metadata" src="{{ $performance->mediaUrl() }}" @if ($performance->poster_path) poster="{{ $performance->posterUrl() }}" @endif
            class="absolute inset-0 size-full object-contain"></video>

        <div x-show="failed" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center text-sm text-white/70">
            <x-ui.icon name="exclamation-triangle" class="size-6 text-amber-400" />
            <p>Lecture impossible dans le navigateur.</p>
            @if ($performance->mediaUrl())<a href="{{ $performance->mediaUrl() }}" target="_blank" rel="noopener" class="font-semibold text-white underline" x-on:click.stop>Ouvrir le fichier</a>@endif
        </div>

        <div x-show="seekLabel" x-cloak class="pointer-events-none absolute inset-0 grid place-items-center">
            <span class="rounded-full bg-black/60 px-4 py-1.5 text-base font-bold text-white" x-text="seekLabel"></span>
        </div>

        <div x-show="(controls || ! playing) && ! failed" x-transition.opacity class="absolute inset-0 bg-black/35">
            @if ($title)
                <p x-show="full" x-cloak class="absolute top-4 left-4 right-4 truncate text-sm font-semibold text-white/80">{{ $title }}</p>
            @endif
            <div class="absolute inset-0 flex items-center justify-center gap-8">
                <button type="button" x-on:click.stop="seek(-10)" class="relative grid size-11 place-items-center rounded-full bg-black/40 text-white transition hover:bg-black/60" title="Reculer de 10 secondes" aria-label="Reculer de 10 secondes">
                    <x-ui.icon name="backward" variant="s" class="size-5" />
                </button>
                <button type="button" x-on:click.stop="toggle()" class="grid size-14 place-items-center rounded-full bg-white text-slate-950 shadow-lg transition hover:scale-105" x-bind:aria-label="ended ? 'Revoir' : (playing ? 'Pause' : 'Lecture')">
                    <span x-show="! playing && ! ended"><x-ui.icon name="play" variant="s" class="ml-0.5 size-7" /></span>
                    <span x-show="playing" x-cloak><x-ui.icon name="pause" variant="s" class="size-7" /></span>
                    <span x-show="ended" x-cloak><x-ui.icon name="arrow-uturn-left" variant="s" class="size-6" /></span>
                </button>
                <button type="button" x-on:click.stop="seek(10)" class="relative grid size-11 place-items-center rounded-full bg-black/40 text-white transition hover:bg-black/60" title="Avancer de 10 secondes" aria-label="Avancer de 10 secondes">
                    <x-ui.icon name="forward" variant="s" class="size-5" />
                </button>
            </div>
            <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 px-3 pb-2" x-on:click.stop>
                <span class="shrink-0 text-xs font-medium text-white tabular-nums" x-text="`${time(current)} / ${time(duration)}`"></span>
                <input type="range" min="0" max="1000" step="1" x-bind:value="progress" x-on:input="scrub($event.target.value)" aria-label="Position dans la vidéo"
                    class="h-1 flex-1 cursor-pointer accent-brand-500">
                <button type="button" x-on:click="cycleSpeed()" class="shrink-0 rounded-full border border-white/50 px-2 py-0.5 text-xs font-bold text-white" x-bind:class="speed !== 1 && 'bg-white/20'" x-text="speedLabel()" aria-label="Vitesse de lecture"></button>
                <button type="button" x-on:click="fullscreen()" class="shrink-0 rounded-lg p-1 text-white hover:bg-white/10" x-bind:aria-label="full ? 'Quitter le plein écran' : 'Plein écran'">
                    <span x-show="! full"><x-ui.icon name="arrows-pointing-out" variant="m" class="size-5" /></span>
                    <span x-show="full" x-cloak><x-ui.icon name="arrows-pointing-in" variant="m" class="size-5" /></span>
                </button>
            </div>
        </div>
    </div>
@endif
