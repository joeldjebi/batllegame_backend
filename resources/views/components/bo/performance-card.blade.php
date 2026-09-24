@props(['performance', 'organizer', 'competition', 'canRun' => false])

{{-- One submission of an online stage: player, provenance, review (AJAX). --}}
@php
    use App\Enums\PerformanceStatus;
@endphp

<div class="rounded-lg bg-white p-3 ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10">
    <div class="mb-2 flex items-center justify-between gap-2">
        <button type="button" x-on:click="$dispatch('open-modal', @js('participant-'.$performance->participant_id))" class="flex min-w-0 items-center gap-2 text-left">
            <x-ui.avatar :name="$performance->participant->stage_name" :src="$performance->participant->user?->avatarUrl()" size="sm" />
            <span class="truncate text-sm font-semibold text-slate-900 hover:text-brand-700 dark:text-white">{{ $performance->participant->stage_name }}</span>
        </button>
        <x-ui.badge :value="$performance->status" />
    </div>
    <x-bo.media-player :performance="$performance" />
    <p class="mt-2 text-xs text-slate-500">
        {{ $performance->media_type?->label() }}
        @if ($performance->duration_seconds) · {{ gmdate('i:s', $performance->duration_seconds) }}@endif
        · {{ number_format(($performance->size_bytes ?? 0) / 1048576, 1, ',', ' ') }} Mo
        · {{ $performance->updated_at->translatedFormat('d M, H:i') }}
    </p>
    <x-bo.media-provenance :media="$performance" :timezone="$competition->settings->timezone" class="mt-2" />
    @if ($performance->rejection_reason)<p class="mt-1 text-xs text-rose-600">{{ $performance->rejection_reason }}</p>@endif
    @if ($canRun && $performance->status === PerformanceStatus::Pending)
        <div class="mt-3 flex gap-2" x-data="{ rejecting: false }">
            <form method="POST" action="{{ route('organizers.competitions.performances.review', [$organizer, $competition, $performance]) }}" x-show="! rejecting" x-data="ajaxForm" x-on:submit.prevent="send">
                @csrf @method('PATCH')
                <input type="hidden" name="decision" value="approve">
                <x-ui.button type="submit" size="xs" icon="check">Valider</x-ui.button>
            </form>
            <x-ui.button size="xs" variant="danger-soft" icon="x-mark" x-show="! rejecting" x-on:click="rejecting = true">Rejeter</x-ui.button>
            <form method="POST" action="{{ route('organizers.competitions.performances.review', [$organizer, $competition, $performance]) }}" x-show="rejecting" x-cloak class="flex w-full gap-2" x-data="ajaxForm" x-on:submit.prevent="send">
                @csrf @method('PATCH')
                <input type="hidden" name="decision" value="reject">
                <input name="reason" required placeholder="Motif du rejet" class="min-w-0 flex-1 rounded-lg border-0 bg-slate-50 py-1 text-xs ring-1 ring-slate-200 focus:ring-2 focus:ring-rose-500 dark:bg-white/5 dark:ring-white/10">
                <x-ui.button type="submit" size="xs" variant="danger">Rejeter</x-ui.button>
            </form>
        </div>
    @endif
</div>
