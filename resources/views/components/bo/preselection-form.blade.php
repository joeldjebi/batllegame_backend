@props(['competition', 'organizer', 'canUpdate' => false])

{{-- Dates, weights and media rules of the pre-selection (inline on creation, in a slide-over afterwards). --}}
@php
    use App\Enums\PreselectionState;

    $preselection = $competition->preselection;
    $state = $preselection?->state();
    $rules = $preselection?->rules ?? \App\Data\PreselectionRules::defaults();
    $publicVote = $competition->settings->publicVotingEnabled;
@endphp

<form method="POST" action="{{ route('organizers.competitions.preselection.update', [$organizer, $competition]) }}" class="space-y-4"
    x-data="{ like: {{ (int) old('rules.like_weight', $rules->likeWeight) }} }">
    @csrf @method('PUT')
    <input type="hidden" name="_form" value="preselection-settings">
    <fieldset @disabled(! $canUpdate || $state === PreselectionState::Published) class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="starts_at" type="datetime-local" label="Début des envois" :value="$preselection?->starts_at" required />
            <x-ui.input name="ends_at" type="datetime-local" label="Fin des envois" :value="$preselection?->ends_at" required hint="Plus aucune prestation acceptée après." />
            @if ($publicVote)
                <x-ui.input name="vote_ends_at" type="datetime-local" label="Fin du vote du public" :value="$preselection?->vote_ends_at" hint="Likes possibles dès la publication d'une prestation. Vide = fin des envois." />
            @else
                <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-white/[0.03]">Vote du public désactivé (Paramètres) : classement 100 % jury.</p>
            @endif
            <x-ui.input name="deliberation_hours" type="number" min="0" max="720" label="Délibération du jury" :value="$preselection?->deliberation_hours ?? 24" suffix="heures" hint="Temps laissé au jury après le vote pour finir ses notes." />
        </div>
        <fieldset @disabled($preselection?->rulesFrozen()) class="space-y-4">
            <x-ui.input name="rules[selection_size]" type="number" min="2" max="1024" label="Nombre d'artistes retenus" :value="$rules->selectionSize" required hint="Participants de l'événement après la présélection." />
            <div @class(['rounded-xl bg-slate-50 p-4 dark:bg-white/[0.03]', 'hidden' => ! $publicVote])>
                <div class="flex justify-between text-sm font-medium">
                    <span class="text-fuchsia-600 dark:text-fuchsia-300">Likes <span x-text="like"></span> %</span>
                    <span class="text-brand-700 dark:text-brand-300">Jury <span x-text="100 - like"></span> %</span>
                </div>
                <input type="range" min="0" max="100" step="5" x-model.number="like" class="mt-3 w-full accent-brand-600">
                <input type="hidden" name="rules[like_weight]" :value="like">
                <input type="hidden" name="rules[jury_weight]" :value="100 - like">
            </div>
            <div class="flex flex-wrap gap-4">
                @foreach (\App\Enums\MediaType::cases() as $type)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rules[media_types][]" value="{{ $type->value }}" @checked(in_array($type, $rules->mediaTypes, true)) class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $type->label() }}</label>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input name="rules[media_max_duration]" type="number" min="10" label="Durée max." :value="$rules->mediaMaxDuration" suffix="sec" />
                <x-ui.input name="rules[media_max_size_mb]" type="number" min="1" label="Taille max." :value="$rules->mediaMaxSizeMb" suffix="Mo" />
            </div>
            @if ($preselection?->rulesFrozen())
                <p class="text-xs text-slate-500">La présélection a commencé : seules les dates restent modifiables.</p>
            @endif
        </fieldset>
        @if ($canUpdate && $state !== PreselectionState::Published)
            <x-ui.button type="submit" class="w-full" icon="check">{{ $preselection ? 'Enregistrer' : 'Créer la présélection' }}</x-ui.button>
        @endif
    </fieldset>
</form>
