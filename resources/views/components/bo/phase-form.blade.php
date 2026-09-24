@props(['competition', 'organizer', 'phase' => null, 'types' => \App\Enums\PhaseType::cases()])

{{-- Create or edit a phase (until it starts), in a slide-over. Only the formats that can
     follow the previous phase are offered ($types). --}}
@php
    use App\Enums\CompetitionMode;
    use App\Enums\GroupDrawMethod;
    use App\Enums\MediaType;
    use App\Enums\PhaseType;
    use App\Enums\TieBreaker;
    use App\Enums\VoteMode;
    use App\Services\Competition\GroupPlan;

    $name = $phase ? 'edit-phase-'.$phase->id : 'create-phase';
    $rules = $phase?->rules;
    // Old input only belongs to the form that was submitted.
    $own = old('_form') === $name;
    $value = fn (string $key, $default) => $own ? old($key, $default) : $default;
    $hybrid = $competition->mode === CompetitionMode::Hybrid;
    $expected = $rules?->expectedEntrants ?? GroupPlan::expectedEntrants($competition, $phase);
    // After a group phase the entrants are its qualifiers: nothing to plan here.
    $previous = $competition->phases->when($phase, fn ($phases) => $phases->where('position', '<', $phase->position))->sortBy('position')->last();
    $afterGroups = $previous?->type === PhaseType::Groups;
    $defaultType = $phase?->type->value ?? (in_array(PhaseType::SingleElimination, $types, true) ? PhaseType::SingleElimination->value : ($types[0]->value ?? null));
    $mediaTypes = $value('rules.media_types', $rules ? array_map(fn (MediaType $t) => $t->value, $rules->mediaTypes) : MediaType::values());
    $tie = $value('rules.tie_breakers.0', $rules?->tieBreakers[0]->value ?? TieBreaker::JuryScore->value) === TieBreaker::PublicScore->value ? 'public' : 'jury';
    $descriptions = [
        PhaseType::Groups->value => 'Chaque artiste présente sa prestation dans sa poule ; le public et le jury les classent, les meilleurs se qualifient.',
        PhaseType::SingleElimination->value => 'Battles à 1 contre 1 : une défaite et c\'est fini. Tableau généré avec exemptés si besoin.',
        PhaseType::DoubleElimination->value => 'Battles à 1 contre 1 : deux défaites pour être éliminé, tableau des perdants.',
    ];
@endphp

<x-ui.slide-over :name="$name" :title="$phase ? 'Modifier la phase '.$phase->position : 'Nouvelle phase'" :description="$phase ? 'Modifiable jusqu\'à son démarrage.' : 'Les règles restent modifiables jusqu\'au démarrage de la phase.'" icon="rectangle-stack"
    :show="$own && $errors->any()">
    <form method="POST" action="{{ $phase ? route('organizers.competitions.phases.update', [$organizer, $competition, $phase]) : route('organizers.competitions.phases.store', [$organizer, $competition]) }}" class="space-y-6"
        x-data="{
            type: @js($value('type', $defaultType)),
            vote: @js($value('rules.vote_mode', $rules?->voteMode->value ?? VoteMode::Mixed->value)),
            jury: {{ (int) $value('rules.jury_weight', $rules && $rules->voteMode === VoteMode::Mixed ? $rules->juryWeight : 50) }},
            mode: @js($value('mode', $phase?->mode?->value ?? ($hybrid ? CompetitionMode::Online->value : ''))),
            inherited: @js($competition->mode->value),
            tie: @js($tie),
            get online() { return (this.mode || this.inherited) === 'en_ligne' },
        }">
        @csrf
        @if ($phase) @method('PUT') @endif
        <input type="hidden" name="_form" value="{{ $name }}">

        <x-ui.field label="Format" :error="$own ? $errors->first('type') : null">
            <div class="grid gap-2">
                @foreach ($types as $type)
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="{{ $type->value }}" x-model="type" class="peer sr-only">
                        <span class="flex items-center gap-3 rounded-xl p-3 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500 hover:bg-slate-50 dark:ring-white/10 dark:peer-checked:bg-brand-500/10">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-brand-600 shadow-soft ring-1 ring-slate-900/5 dark:bg-white/10 dark:text-brand-300"><x-ui.icon :name="$type->icon()" class="size-5" /></span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $type->label() }}</span>
                                <span class="block text-xs text-slate-500">{{ $descriptions[$type->value] }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($phase && count($types) < count(PhaseType::cases()))
                <p class="mt-2 text-xs text-slate-500">Une autre phase suit celle-ci : seules des poules peuvent qualifier des artistes pour la suivante.</p>
            @endif
        </x-ui.field>

        {{-- Groups: format assistant (App\Services\Competition\GroupPlan, groupPlanner in app.js) --}}
        <div x-show="type === 'poules'" x-collapse>
            <div x-data="groupPlanner({ entrants: {{ (int) $value('rules.expected_entrants', $expected) ?: 'null' }}, groups: {{ (int) $value('rules.group_count', $rules?->groupCount ?? 2) }}, qualifiers: {{ (int) $value('qualifiers_per_group', $phase?->qualifiers_per_group ?? 2) }}, suggest: @js(! $phase && ! $own) })"
                class="space-y-4 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-900/5 dark:bg-white/[0.03] dark:ring-white/10">
                <x-ui.input name="rules[expected_entrants]" type="number" min="2" max="1024" label="Participants attendus" x-model.number="n" x-bind:disabled="type !== 'poules'" icon="users"
                    :hint="$expected ? 'Pré-rempli d\'après votre compétition : ajustez si besoin. Vérifié à nouveau au lancement.' : 'Combien d\'artistes joueront cette phase ? Vérifié à nouveau au lancement.'" />

                <div x-show="suggestions.length" class="space-y-2">
                    <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Formules conseillées</p>
                    <div class="grid gap-2">
                        <template x-for="option in suggestions" :key="option.g + '-' + option.q">
                            <button type="button" x-on:click="apply(option)"
                                class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2.5 text-left text-sm ring-1 ring-slate-200 transition hover:ring-brand-400 dark:bg-white/5 dark:ring-white/10"
                                x-bind:class="g === option.g && q === option.q && '!ring-2 !ring-brand-500'">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-slate-900 dark:text-white" x-text="option.label"></span>
                                    <span class="block text-xs text-slate-500" x-text="'Puis ' + option.next"></span>
                                </span>
                                <x-ui.icon name="check-circle" variant="s" class="size-5 shrink-0 text-brand-600" x-show="g === option.g && q === option.q" />
                            </button>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <x-ui.input name="rules[group_count]" type="number" min="1" label="Nombre de poules" x-model.number="g" />
                    <x-ui.input name="qualifiers_per_group" type="number" min="1" label="Qualifiés / poule" x-model.number="q" />
                    <x-ui.select name="rules[draw_method]" label="Répartition" :options="GroupDrawMethod::options()" :value="$value('rules.draw_method', $rules?->drawMethod->value)" class="col-span-2 sm:col-span-1" />
                </div>
                <p class="-mt-2 text-xs text-slate-500">Tirage au sort : poules au hasard. Tête de série : les meilleurs (n° saisis dans l'onglet Participants, sinon l'ordre d'inscription) sont répartis dans des poules différentes.</p>

                <div x-show="n" class="rounded-xl p-3 text-sm ring-1" x-bind:class="problem ? 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20' : 'bg-white text-slate-700 ring-slate-200 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10'">
                    <p x-show="problem" class="flex gap-2"><x-ui.icon name="exclamation-triangle" variant="m" class="mt-0.5 size-4 shrink-0" /><span x-text="problem"></span></p>
                    <template x-if="summary">
                        <ul class="space-y-1.5">
                            <li class="flex gap-2"><x-ui.icon name="squares-2x2" variant="m" class="mt-0.5 size-4 shrink-0 text-brand-600" /><span x-text="summary.groups"></span></li>
                            <li class="flex gap-2"><x-ui.icon name="microphone" variant="m" class="mt-0.5 size-4 shrink-0 text-brand-600" /><span x-text="summary.matches"></span></li>
                            <li class="flex gap-2"><x-ui.icon name="arrow-trending-up" variant="m" class="mt-0.5 size-4 shrink-0 text-emerald-600" /><span x-text="summary.next"></span></li>
                            <li x-show="summary.uneven" class="flex gap-2 text-xs text-slate-500"><x-ui.icon name="information-circle" variant="m" class="size-4 shrink-0" /><span>Poules de tailles différentes : le même nombre d'artistes se qualifie dans chacune.</span></li>
                        </ul>
                    </template>
                </div>
            </div>
        </div>
        @unless ($afterGroups)
            <div x-show="type !== 'poules'" x-collapse>
                <x-ui.input name="rules[expected_entrants]" type="number" min="2" max="1024" label="Participants attendus" icon="users" :value="$value('rules.expected_entrants', $expected)"
                    x-bind:disabled="type === 'poules'" hint="Sert à prévoir les tours jusqu'à la finale (quarts, demies…) et leur calendrier." />
            </div>
        @endunless
        <label x-show="type === 'double_elimination'" x-collapse class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="rules[grand_final_reset]" value="1" @checked($value('rules.grand_final_reset', $rules?->grandFinalReset)) class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Grande finale « reset » si le finaliste du tableau des perdants gagne
        </label>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select name="mode" label="Mode" x-model="mode"
                :options="collect(CompetitionMode::options())->except(CompetitionMode::Hybrid->value)->all()"
                :placeholder="$hybrid ? null : 'Hérité ('.$competition->mode->label().')'"
                :hint="$hybrid ? 'Compétition mixte : choisissez pour chaque phase.' : null" />
            <x-ui.input name="rules[rounds]" type="number" min="1" max="10" label="Passages par artiste" :value="$rules?->rounds ?? 1" hint="Nombre de passages de chaque artiste." />
            <x-ui.input name="rules[turn_duration]" type="number" min="15" label="Durée d'un passage" :value="$rules?->turnDuration ?? 60" suffix="sec" />
        </div>

        <x-ui.field label="Qui décide ?">
            <div class="grid grid-cols-3 gap-2">
                @foreach (VoteMode::cases() as $mode)
                    <label class="cursor-pointer">
                        <input type="radio" name="rules[vote_mode]" value="{{ $mode->value }}" x-model="vote" class="peer sr-only">
                        <span class="block rounded-xl p-2.5 text-center text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-2 peer-checked:ring-brand-500 dark:text-slate-300 dark:ring-white/10 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">{{ $mode->label() }}</span>
                    </label>
                @endforeach
            </div>
        </x-ui.field>

        <div x-show="vote === 'mixte'" x-collapse class="rounded-xl bg-slate-50 p-4 dark:bg-white/[0.03]">
            <div class="flex justify-between text-sm font-medium">
                <span class="text-brand-700 dark:text-brand-300">Jury <span x-text="jury"></span> %</span>
                <span class="text-fuchsia-600 dark:text-fuchsia-300">Public <span x-text="100 - jury"></span> %</span>
            </div>
            <input type="range" min="5" max="95" step="5" x-model.number="jury" class="mt-3 w-full accent-brand-600">
            <input type="hidden" name="rules[jury_weight]" :value="jury">
            <input type="hidden" name="rules[public_weight]" :value="100 - jury">
        </div>

        <div x-show="online" x-collapse class="space-y-4 rounded-xl bg-slate-50 p-4 dark:bg-white/[0.03]">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100"><x-ui.icon name="cloud-arrow-up" class="size-5 text-brand-600" /> Prestations en ligne</p>
            <p class="-mt-2 text-xs text-slate-500">Une prestation par artiste et par étape. Sans prestation à la date limite : forfait.</p>
            <div class="flex flex-wrap gap-4">
                @foreach (MediaType::cases() as $type)
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="rules[media_types][]" value="{{ $type->value }}" @checked(in_array($type->value, (array) $mediaTypes, true)) class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $type->label() }}
                    </label>
                @endforeach
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="rules[media_max_duration]" type="number" min="10" max="1800" label="Durée maximale" :value="$rules?->mediaMaxDuration ?? 180" suffix="sec" />
                <x-ui.input name="rules[media_max_size_mb]" type="number" min="1" max="2048" label="Taille maximale" :value="$rules?->mediaMaxSizeMb ?? 200" suffix="Mo" />
            </div>
        </div>

        {{-- Advanced --}}
        <div x-data="{ open: @js($tie === 'public') }" class="rounded-xl ring-1 ring-slate-200 dark:ring-white/10">
            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <span class="flex items-center gap-2"><x-ui.icon name="adjustments-horizontal" variant="m" class="size-4 text-slate-400" /> Avancé · départage des égalités</span>
                <x-ui.icon name="chevron-down" variant="m" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
            </button>
            <div x-show="open" x-collapse x-cloak class="space-y-3 border-t border-slate-100 px-4 py-3 dark:border-white/5">
                <p class="text-xs text-slate-500">À note finale égale, on compare d'abord…</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['jury' => 'La note du jury', 'public' => 'Le vote du public'] as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" value="{{ $key }}" x-model="tie" class="peer sr-only">
                            <span class="block rounded-xl p-2.5 text-center text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-2 peer-checked:ring-brand-500 dark:text-slate-300 dark:ring-white/10 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500" x-text="'Puis ' + (tie === 'jury' ? 'le vote du public' : 'la note du jury') + ', puis la tête de série. ' + (type === 'poules' ? 'En dernier recours : l\'ordre d\'inscription.' : 'En dernier recours, vous désignez le vainqueur à la clôture du battle.')"></p>
                <template x-for="item in (tie === 'jury' ? ['jury', 'public', 'seed'] : ['public', 'jury', 'seed'])" :key="item">
                    <input type="hidden" name="rules[tie_breakers][]" :value="item">
                </template>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/10">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $name }}')">Annuler</x-ui.button>
            <x-ui.button type="submit" variant="primary" :icon="$phase ? 'check' : 'plus'">{{ $phase ? 'Enregistrer' : 'Ajouter la phase' }}</x-ui.button>
        </div>
    </form>
</x-ui.slide-over>
