@props([
    'action',
    'method' => 'POST',
    'title',
    'message' => null,
    'confirm' => 'Confirmer',
    'danger' => true,
    'icon' => 'exclamation-triangle',
])

{{-- A trigger (default slot) that opens a confirmation dialog before submitting. --}}
@php($modal = 'confirm-'.substr(md5($action.$method.$title), 0, 10))

<span x-data class="contents" x-on:click="$dispatch('open-modal', @js($modal))">{{ $slot }}</span>

@push('modals')
    <x-ui.modal :name="$modal" :title="$title" :description="$message" :icon="$icon" :danger="$danger" max-width="md">
        <form method="POST" action="{{ $action }}" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            @csrf
            @if (strtoupper($method) !== 'POST') @method($method) @endif
            {{ $fields ?? '' }}
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $modal }}')">Annuler</x-ui.button>
            <x-ui.button type="submit" :variant="$danger ? 'danger' : 'primary'">{{ $confirm }}</x-ui.button>
        </form>
    </x-ui.modal>
@endpush
