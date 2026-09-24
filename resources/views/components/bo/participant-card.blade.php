@props(['participant', 'competition'])

{{-- « Fiche » of a participant for the organizer: personal details, payments, pre-selection. --}}
@php
    use App\Enums\PaymentStatus;

    $user = $participant->user;
    $modal = 'participant-'.$participant->id;
    $entry = $participant->preselectionEntry;
    $whatsapp = 'https://wa.me/'.ltrim((string) $user->phone, '+');
    $fields = [
        ['user', 'Nom complet', $user->name],
        ['phone', 'Téléphone', $user->phone],
        ['envelope', 'Email', $user->email ?? '—'],
        ['map-pin', 'Ville', $user->locationLabel() ?? '—'],
        ['globe-europe-africa', 'Pays', trim(($user->country?->flag ?? '').' '.($user->country?->name ?? '—'))],
        ['check-badge', 'Numéro vérifié', $user->phone_verified_at ? 'Oui, le '.$user->phone_verified_at->translatedFormat('d M Y') : 'Non'],
        ['calendar', 'Compte créé le', $user->created_at->translatedFormat('d M Y')],
        ['user-plus', 'Inscrit à la compétition le', $participant->created_at->translatedFormat('d M Y à H:i')],
    ];
@endphp

@push('modals')
    <x-ui.modal :name="$modal" max-width="2xl">
        <div class="-mx-6 -mt-5 flex flex-col items-center gap-3 bg-slate-50 px-6 pt-8 pb-6 text-center sm:flex-row sm:text-left dark:bg-white/[0.03]">
            @if ($user->avatarUrl())
                <a href="{{ $user->avatarUrl() }}" target="_blank" rel="noopener"><img src="{{ $user->avatarUrl() }}" alt="{{ $participant->stage_name }}" class="size-24 rounded-full object-cover ring-4 ring-white shadow-lift dark:ring-slate-900"></a>
            @else
                <x-ui.avatar :name="$participant->stage_name" size="xl" class="!size-24 ring-4 shadow-lift" />
            @endif
            <div class="min-w-0 sm:ml-2">
                <h3 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $participant->stage_name }}</h3>
                <div class="mt-2 flex flex-wrap justify-center gap-1.5 sm:justify-start">
                    <x-ui.badge :value="$participant->status" />
                    @if ($participant->seed)<x-ui.badge tone="gray" :dot="false">Tête de série {{ $participant->seed }}</x-ui.badge>@endif
                    @if ($entry)<x-ui.badge :tone="$entry->status->tone()" :dot="false" icon="film">Prestation : {{ mb_strtolower($entry->status->label()) }}</x-ui.badge>@endif
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
                    <x-ui.button size="sm" variant="secondary" icon="phone" :href="'tel:'.$user->phone">Appeler</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" icon="chat-bubble-left-right" :href="$whatsapp" target="_blank" rel="noopener">WhatsApp</x-ui.button>
                    @if ($user->email)<x-ui.button size="sm" variant="secondary" icon="envelope" :href="'mailto:'.$user->email">Email</x-ui.button>@endif
                </div>
            </div>
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            @foreach ($fields as [$icon, $label, $value])
                <div class="flex gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-white/5"><x-ui.icon :name="$icon" variant="m" class="size-4" /></span>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $value }}</dd>
                    </div>
                </div>
            @endforeach
        </dl>

        <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/10">
            <h4 class="mb-3 text-sm font-semibold">Paiements</h4>
            @forelse ($participant->payments as $payment)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-white/5 {{ ! $loop->last ? 'mb-2' : '' }}">
                    <span class="font-semibold tabular-nums">{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</span>
                    <span class="text-xs text-slate-500">{{ $payment->method->label() }} · {{ $payment->reference }} · {{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('d M Y, H:i') }}</span>
                    <x-ui.badge :tone="$payment->status === PaymentStatus::Paid ? 'green' : ($payment->status === PaymentStatus::Failed ? 'red' : 'amber')" :dot="false">{{ $payment->status->label() }}</x-ui.badge>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ $competition->requiresPayment() ? 'Aucun paiement pour le moment.' : 'Compétition gratuite.' }}</p>
            @endforelse
        </div>
    </x-ui.modal>
@endpush

<button type="button" x-on:click="$dispatch('open-modal', @js($modal))" {{ $attributes->class('flex min-w-0 items-center gap-3 text-left') }}>
    <x-ui.avatar :name="$participant->stage_name" :src="$user->avatarUrl()" size="sm" />
    <span class="min-w-0">
        <span class="block truncate font-semibold text-slate-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-300">{{ $participant->stage_name }}</span>
        <span class="block text-xs text-brand-600 dark:text-brand-300">Voir la fiche</span>
    </span>
</button>
