@props(['media', 'timezone' => 'Africa/Abidjan', 'compact' => false, 'expanded' => false])

{{-- Provenance of an uploaded media (hidden metadata), for the organizer only.
     An indication to help the review, never a proof: metadata can be removed or edited. --}}
@php
    use App\Enums\PerformanceStatus;
    use App\Enums\RecordingPeriod;

    $period = $media->recordingPeriod();
    $origin = $media->media_origin;
    $meta = $media->media_metadata ?? [];
    [$start, $end] = $media->submissionWindow();
    $fmt = fn ($date) => $date?->copy()->setTimezone($timezone)->translatedFormat('d M Y, H:i');
    $originLabel = match (true) {
        $origin === null => null,
        filled($meta['app'] ?? null) => 'Montage : '.$meta['app'],
        filled($meta['platform'] ?? null) => 'Probablement '.$meta['platform'],
        filled($meta['device'] ?? null) && $origin === \App\Enums\MediaOrigin::Device => $meta['device'],
        default => $origin->label(),
    };
    $rows = array_filter([
        'Enregistré le' => $media->recorded_at ? $fmt($media->recorded_at).(isset($meta['date_source']) ? ' ('.$meta['date_source'].')' : '') : 'Non indiqué dans le fichier',
        'Période attendue' => $start ? $fmt($start).' → '.($end ? $fmt($end) : '…') : null,
        'Envoyé le' => $fmt($media->uploadedAt()),
        'Date du fichier sur l\'appareil' => $media->client_modified_at ? $fmt($media->client_modified_at).' (indicatif)' : null,
        'Appareil' => $meta['device'] ?? null,
        'Logiciel' => $meta['software'] ?? null,
        'Application de montage' => $meta['app'] ?? null,
        'Plateforme détectée' => $meta['platform'] ?? null,
        'Encodeur' => $meta['encoder'] ?? null,
        'Version Android' => $meta['android_version'] ?? null,
        'Position GPS' => ! empty($meta['has_location']) ? 'Présente (non conservée)' : null,
        'Format' => $meta['brand'] ?? null,
    ]);
@endphp

<div x-data="{ open: @js($expanded) }" {{ $attributes->class('text-xs') }}>
    @if ($origin === null)
        <p class="text-slate-400">{{ $media->status === PerformanceStatus::Processing ? 'Analyse des métadonnées en cours…' : 'Métadonnées non analysées.' }}</p>
    @else
        <div class="flex flex-wrap items-center gap-1.5">
            <x-ui.badge :tone="$period->tone()" :dot="false" :icon="match ($period) { RecordingPeriod::During => 'calendar-days', RecordingPeriod::Before => 'exclamation-triangle', default => 'question-mark-circle' }">
                {{ $period === RecordingPeriod::Before ? 'Enregistré le '.$media->recorded_at->copy()->setTimezone($timezone)->translatedFormat('d M Y').', avant la période' : $period->label() }}
            </x-ui.badge>
            <x-ui.badge :tone="$origin->tone()" :dot="false" :icon="match ($origin) { \App\Enums\MediaOrigin::Device => 'device-phone-mobile', \App\Enums\MediaOrigin::EditingApp => 'scissors', \App\Enums\MediaOrigin::Platform => 'globe-alt', default => 'finger-print' }">{{ $originLabel }}</x-ui.badge>
            @unless ($compact || $expanded)
                <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-0.5 font-semibold text-brand-700 hover:text-brand-600 dark:text-brand-300">
                    <span x-text="open ? 'Masquer' : 'Détails'"></span><x-ui.icon name="chevron-down" variant="m" class="size-3.5 transition" x-bind:class="open && 'rotate-180'" />
                </button>
            @endunless
        </div>
        @unless ($compact)
        <div x-show="open" x-collapse x-cloak>
            <dl class="mt-2 space-y-2 rounded-lg bg-slate-50 p-2.5 dark:bg-white/[0.03]">
                @foreach ($rows as $label => $value)
                    <div><dt class="text-[11px] text-slate-500">{{ $label }}</dt><dd class="font-medium break-words text-slate-800 dark:text-slate-200">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-1.5 text-slate-500">{{ $origin->hint() }} Indice seulement : les métadonnées peuvent être effacées ou modifiées.</p>
        </div>
        @endunless
    @endif
</div>
