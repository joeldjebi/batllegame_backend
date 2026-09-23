@php use App\Enums\PaymentMethod; @endphp

<x-layouts.portal title="Paiement">
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Frais d'inscription" :breadcrumbs="['Mon espace' => route('artist.dashboard'), $competition->name => null]" />

        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
            <x-ui.icon name="beaker" class="size-5 shrink-0" />
            <p><strong>Paiement simulé.</strong> Aucun moyen de paiement réel n'est encore branché : aucune somme n'est prélevée. Choisissez le résultat à simuler.</p>
        </div>

        <x-ui.card :padding="false">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 dark:border-white/5">
                <div>
                    <p class="font-display text-lg font-semibold">{{ $competition->name }}</p>
                    <p class="text-sm text-slate-500">{{ $competition->organizer->name }} · nom de scène : {{ $participant->stage_name }}</p>
                </div>
                <p class="font-display text-3xl font-extrabold tabular-nums">{{ number_format($competition->entry_fee, 0, ',', ' ') }} <span class="text-base font-semibold text-slate-400">{{ $competition->currency }}</span></p>
            </div>

            <form method="POST" action="{{ route('artist.competitions.payment', $competition) }}" class="space-y-6 p-6" x-data="{ method: '{{ old('method', PaymentMethod::OrangeMoney->value) }}' }">
                @csrf
                @if ($lastFailure)
                    <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300">Dernière tentative refusée ({{ $lastFailure->reference }}). Vous pouvez réessayer.</p>
                @endif

                <x-ui.field label="Moyen de paiement">
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (PaymentMethod::cases() as $method)
                            <label class="cursor-pointer">
                                <input type="radio" name="method" value="{{ $method->value }}" x-model="method" class="peer sr-only">
                                <span class="flex items-center gap-3 rounded-xl p-3 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500 hover:bg-slate-50 dark:ring-white/10 dark:peer-checked:bg-brand-500/10">
                                    <span class="grid size-9 place-items-center rounded-lg bg-white text-brand-600 shadow-soft ring-1 ring-slate-900/5 dark:bg-white/10 dark:text-brand-300"><x-ui.icon :name="$method->icon()" class="size-5" /></span>
                                    <span class="text-sm font-semibold">{{ $method->label() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-ui.field>

                <x-ui.field label="Résultat à simuler">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="outcome" value="succes" checked class="text-brand-600 focus:ring-brand-500"> Paiement accepté</label>
                        <label class="flex items-center gap-2"><input type="radio" name="outcome" value="echec" class="text-rose-600 focus:ring-rose-500"> Paiement refusé</label>
                    </div>
                </x-ui.field>

                <x-ui.button type="submit" size="lg" class="w-full" icon="lock-closed">Payer {{ number_format($competition->entry_fee, 0, ',', ' ') }} {{ $competition->currency }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.portal>
