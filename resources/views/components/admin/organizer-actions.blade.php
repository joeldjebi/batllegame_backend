@props(['organizer', 'size' => 'md'])

@if ($organizer->status !== \App\Enums\OrganizerStatus::Verified)
    <x-ui.confirm :action="route('admin.organizers.status', $organizer)" method="PATCH" :danger="false" icon="check-badge"
        :title="'Vérifier '.$organizer->name.' ?'" message="L'organisateur pourra ouvrir des inscriptions publiques." confirm="Vérifier">
        <x-slot:fields><input type="hidden" name="status" value="verifie"></x-slot:fields>
        <x-ui.button :size="$size" icon="check-badge">{{ $organizer->isSuspended() ? 'Réactiver' : 'Vérifier' }}</x-ui.button>
    </x-ui.confirm>
@endif
@unless ($organizer->isSuspended())
    <x-ui.confirm :action="route('admin.organizers.status', $organizer)" method="PATCH" icon="no-symbol"
        :title="'Suspendre '.$organizer->name.' ?'" message="Toutes ses compétitions seront figées : aucune modification, aucun vote." confirm="Suspendre">
        <x-slot:fields><input type="hidden" name="status" value="suspendu"></x-slot:fields>
        <x-ui.button :size="$size" variant="danger-soft" icon="no-symbol">Suspendre</x-ui.button>
    </x-ui.confirm>
@endunless
