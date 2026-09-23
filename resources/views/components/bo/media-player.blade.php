@props(['performance', 'preload' => 'metadata'])

{{-- Plays an uploaded media; explains the failure (missing file, storage link, unsupported format) instead of a dead player. --}}
<div x-data="{ failed: false }" {{ $attributes->only('class') }}>
    @if ($performance->media_type === \App\Enums\MediaType::Audio)
        <audio controls preload="none" src="{{ $performance->mediaUrl() }}" x-show="! failed" x-on:error="failed = true" class="w-full"></audio>
    @else
        <video controls playsinline preload="{{ $preload }}" src="{{ $performance->mediaUrl() }}" x-show="! failed" x-on:error="failed = true"
            class="aspect-video w-full rounded-lg bg-slate-900 object-contain"></video>
    @endif
    <div x-show="failed" x-cloak class="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg bg-slate-100 p-4 text-center text-sm text-slate-500 dark:bg-white/5">
        <x-ui.icon name="exclamation-triangle" class="size-6 text-amber-500" />
        <p>Lecture impossible dans le navigateur (fichier introuvable ou format non pris en charge).</p>
        @if ($performance->mediaUrl())<a href="{{ $performance->mediaUrl() }}" target="_blank" rel="noopener" class="font-semibold text-brand-700 dark:text-brand-300">Ouvrir le fichier</a>@endif
    </div>
</div>
