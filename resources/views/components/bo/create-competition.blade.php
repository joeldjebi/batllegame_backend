@props(['organizer'])

@php
    use App\Enums\CompetitionMode;
    use App\Enums\Discipline;
@endphp

{{-- Slide-over « Nouvelle compétition », opened with $dispatch('open-modal', 'create-competition'). --}}
<x-ui.slide-over name="create-competition" title="Nouvelle compétition" description="Elle sera créée en brouillon : vous pourrez ajouter les phases, le jury et les critères." icon="trophy"
    :show="$errors->hasAny(['name', 'description', 'discipline', 'mode', 'registration_ends_at', 'max_participants', 'entry_fee'])">
    <form method="POST" action="{{ route('organizers.competitions.store', $organizer) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="_form" value="create-competition">
        <x-ui.input name="name" label="Nom de la compétition" placeholder="Ex. Abidjan Rap Contest 2026" required />
        <x-ui.rich-editor name="description" label="Description" placeholder="Concept, déroulé, règles, lieu…" hint="Les récompenses s'ajoutent ensuite dans les paramètres de la compétition." />

        <x-ui.field label="Discipline">
            <div class="grid grid-cols-3 gap-2">
                @foreach (Discipline::cases() as $discipline)
                    <label class="cursor-pointer">
                        <input type="radio" name="discipline" value="{{ $discipline->value }}" class="peer sr-only" @checked(old('discipline', 'rap') === $discipline->value)>
                        <span class="flex flex-col items-center gap-1.5 rounded-xl p-3 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-2 peer-checked:ring-brand-500 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">
                            <x-ui.icon :name="$discipline->icon()" class="size-5" />{{ $discipline->label() }}
                        </span>
                    </label>
                @endforeach
            </div>
        </x-ui.field>

        <x-ui.field label="Mode">
            <div class="grid grid-cols-3 gap-2">
                @foreach (CompetitionMode::cases() as $mode)
                    <label class="cursor-pointer">
                        <input type="radio" name="mode" value="{{ $mode->value }}" class="peer sr-only" @checked(old('mode', 'presentiel') === $mode->value)>
                        <span class="flex flex-col items-center gap-1.5 rounded-xl p-3 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-2 peer-checked:ring-brand-500 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/10 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">
                            <x-ui.icon :name="$mode->icon()" class="size-5" />{{ $mode->label() }}
                        </span>
                    </label>
                @endforeach
            </div>
        </x-ui.field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="registration_ends_at" type="datetime-local" label="Fin des inscriptions" />
            <x-ui.input name="max_participants" type="number" min="2" label="Participants max." placeholder="32" />
            <x-ui.input name="entry_fee" type="number" min="0" label="Frais d'inscription" value="0" suffix="XOF" />
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/10">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'create-competition')">Annuler</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="sparkles">Créer la compétition</x-ui.button>
        </div>
    </form>
</x-ui.slide-over>
