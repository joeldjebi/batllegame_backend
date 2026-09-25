@php
    use App\Data\PreselectionRules;
    use App\Enums\CompetitionMode;
    use App\Enums\Discipline;
    use App\Enums\GroupDrawMethod;
    use App\Enums\MediaType;
    use App\Enums\VoteMode;

    // Back on the step of the first error.
    $keys = collect($errors->keys());
    $start = match (true) {
        $keys->contains(fn ($k) => ! str_starts_with($k, 'preselection') && ! str_starts_with($k, 'phase')) => 1,
        $keys->contains(fn ($k) => str_starts_with($k, 'preselection')) => 2,
        $keys->isNotEmpty() => 3,
        default => 1,
    };
    $pre = PreselectionRules::defaults();
    $steps = [
        ['trophy', 'La compétition', 'Identité, lieu et inscriptions'],
        ['funnel', 'Présélection', 'Filtrer les inscrits avant la compétition'],
        ['rectangle-stack', 'Format', 'Poules, élimination, jusqu\'à la finale'],
    ];
    $initial = [
        'step' => $start,
        'name' => old('name', ''),
        'mode' => old('mode', CompetitionMode::OnSite->value),
        'max' => (int) old('max_participants') ?: null,
        'fee' => (int) old('entry_fee', 0),
        'withPre' => (bool) old('with_preselection', false),
        'selection' => (int) old('preselection.rules.selection_size', $pre->selectionSize),
        'like' => (int) old('preselection.rules.like_weight', $pre->likeWeight),
        'preEnd' => old('preselection.ends_at', ''),
        'type' => old('phase.type', 'poules'),
        'vote' => old('phase.rules.vote_mode', VoteMode::Mixed->value),
        'jury' => (int) old('phase.rules.jury_weight', 60),
    ];
    $card = 'rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-900/5 sm:p-8 dark:bg-slate-900/60 dark:ring-white/10';
    $choice = 'flex h-full cursor-pointer flex-col gap-1 rounded-2xl p-4 ring-1 ring-slate-200 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500 hover:bg-slate-50 dark:ring-white/10 dark:peer-checked:bg-brand-500/10 dark:hover:bg-white/[0.03]';
@endphp

<x-layouts.app title="Nouvelle compétition">
    <x-ui.page-header title="Nouvelle compétition" :breadcrumbs="['Tableau de bord' => route('dashboard'), $organizer->name => route('organizers.show', $organizer), 'Compétitions' => route('organizers.competitions.index', $organizer), 'Nouvelle' => null]"
        description="Trois étapes : tout est créé d'un coup, en brouillon, et reste modifiable ensuite." />

    <form method="POST" action="{{ route('organizers.competitions.store', $organizer) }}" novalidate
        x-data="competitionWizard(@js($initial))" x-on:submit="submit($event)"
        x-on:keydown.enter="if (step < 3 && $event.target.tagName === 'INPUT') { $event.preventDefault(); go(step + 1) }">
        @csrf
        <input type="hidden" name="_form" value="create-competition">

        {{-- Stepper --}}
        <ol class="mb-8 grid grid-cols-3 gap-2 sm:gap-4">
            @foreach ($steps as $i => [$icon, $label, $hint])
                <li>
                    <button type="button" x-on:click="go({{ $i + 1 }})" class="group flex w-full items-center gap-3 rounded-2xl p-2 text-left transition sm:p-3"
                        x-bind:class="step === {{ $i + 1 }} ? 'bg-white shadow-soft ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10' : 'hover:bg-white/60 dark:hover:bg-white/[0.03]'">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl transition"
                            x-bind:class="step > {{ $i + 1 }} ? 'bg-emerald-500 text-white' : (step === {{ $i + 1 }} ? 'bg-brand-600 text-white shadow-lift' : 'bg-slate-100 text-slate-400 dark:bg-white/5')">
                            <x-ui.icon name="check" variant="m" class="size-5" x-show="step > {{ $i + 1 }}" x-cloak />
                            <x-ui.icon :name="$icon" class="size-5" x-show="step <= {{ $i + 1 }}" />
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Étape {{ $i + 1 }}</span>
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</span>
                            <span class="hidden truncate text-xs text-slate-500 lg:block">{{ $hint }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-6">
                {{-- 1. The competition --}}
                <section x-ref="step1" data-step="1" x-show="step === 1" @if ($start !== 1) x-cloak @endif class="space-y-6">
                    <div class="{{ $card }} space-y-6">
                        <div>
                            <h2 class="font-display text-xl font-bold">La compétition</h2>
                            <p class="mt-1 text-sm text-slate-500">Ce que les artistes et le public découvriront en premier.</p>
                        </div>
                        <x-ui.input name="name" label="Nom de la compétition" placeholder="Ex. Abidjan Rap Contest 2026" required x-model="name" />
                        <x-ui.rich-editor name="description" label="Description" placeholder="Concept, esprit, lieu…" hint="Le déroulé, le règlement et les récompenses se complètent ensuite dans les paramètres." />
                    </div>

                    <div class="{{ $card }} space-y-6">
                        <x-ui.field label="Discipline">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach (Discipline::cases() as $discipline)
                                    <label>
                                        <input type="radio" name="discipline" value="{{ $discipline->value }}" class="peer sr-only" @checked(old('discipline', 'rap') === $discipline->value)>
                                        <span class="{{ $choice }} items-center text-center">
                                            <x-ui.icon :name="$discipline->icon()" class="size-6 text-brand-600" />
                                            <span class="text-sm font-semibold">{{ $discipline->label() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>

                        <x-ui.field label="Mode">
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ([CompetitionMode::OnSite->value => 'Sur scène, vote en direct.', CompetitionMode::Online->value => 'Vidéos envoyées en ligne.', CompetitionMode::Hybrid->value => 'En ligne puis sur scène.'] as $value => $hint)
                                    @php $mode = CompetitionMode::from($value); @endphp
                                    <label>
                                        <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="peer sr-only">
                                        <span class="{{ $choice }}">
                                            <span class="flex items-center gap-2 text-sm font-semibold"><x-ui.icon :name="$mode->icon()" variant="m" class="size-5 text-brand-600" />{{ $mode->label() }}</span>
                                            <span class="text-xs text-slate-500">{{ $hint }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>

                        <x-location-select label="Lieu (ville)" hint="Où se déroule la compétition (présentiel), ou d'où elle est organisée." />
                    </div>

                    <div class="{{ $card }}">
                        <h3 class="mb-5 font-display text-lg font-bold">Inscriptions</h3>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <x-ui.input name="registration_ends_at" type="datetime-local" label="Fin des inscriptions" />
                            <x-ui.input name="max_participants" type="number" min="2" label="Inscrits max." placeholder="32" x-model.number="max" />
                            <x-ui.input name="entry_fee" type="number" min="0" label="Frais d'inscription" value="0" suffix="XOF" x-model.number="fee" />
                        </div>
                    </div>
                </section>

                {{-- 2. Pre-selection --}}
                <section x-ref="step2" data-step="2" x-show="step === 2" @if ($start !== 2) x-cloak @endif class="space-y-6">
                    <div class="{{ $card }} space-y-6">
                        <div>
                            <h2 class="font-display text-xl font-bold">Présélection</h2>
                            <p class="mt-1 text-sm text-slate-500">Elle filtre les inscrits <strong>avant</strong> la compétition : chacun envoie une vidéo, le public like, le jury note, vous retenez les meilleurs. Elle n'a rien à voir avec les poules.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([0 => ['users', 'Sans présélection', 'Tous les inscrits validés participent.'], 1 => ['funnel', 'Avec présélection', 'Seuls les meilleurs sont retenus.']] as $value => [$icon, $label, $hint])
                                <label>
                                    <input type="radio" name="with_preselection" value="{{ $value }}" class="peer sr-only" x-bind:checked="withPre === @js((bool) $value)" x-on:change="withPre = @js((bool) $value)">
                                    <span class="{{ $choice }} !p-5">
                                        <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$icon" class="size-5" /></span>
                                        <span class="mt-2 text-base font-semibold">{{ $label }}</span>
                                        <span class="text-sm text-slate-500">{{ $hint }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <fieldset x-show="withPre" x-collapse x-bind:disabled="! withPre" class="space-y-6">
                        <div class="{{ $card }}">
                            <h3 class="font-display text-lg font-bold">Calendrier</h3>
                            <p class="mb-5 mt-1 text-sm text-slate-500">Chaque artiste envoie sa prestation dès que son inscription est validée : la date qui compte est la date limite d'envoi.</p>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui.input name="preselection[ends_at]" type="datetime-local" label="Date limite d'envoi" x-bind:required="withPre" x-model="preEnd" class="sm:col-span-2" />
                                <x-ui.input name="preselection[vote_ends_at]" type="datetime-local" label="Fin du vote du public" hint="Vide : fin des envois." />
                                <x-ui.input name="preselection[deliberation_hours]" type="number" min="0" max="720" label="Délibération du jury" value="24" suffix="heures" />
                            </div>
                        </div>
                        <div class="{{ $card }} space-y-5">
                            <h3 class="font-display text-lg font-bold">Sélection</h3>
                            <x-ui.input name="preselection[rules][selection_size]" type="number" min="2" max="1024" label="Artistes retenus" x-model.number="selection" hint="Ils forment la compétition : c'est la base du format (étape suivante)." />
                            <div class="rounded-2xl bg-slate-50 p-5 dark:bg-white/[0.03]">
                                <div class="flex justify-between text-sm font-semibold">
                                    <span class="text-fuchsia-600 dark:text-fuchsia-300">Likes <span x-text="like"></span> %</span>
                                    <span class="text-brand-700 dark:text-brand-300">Jury <span x-text="100 - like"></span> %</span>
                                </div>
                                <input type="range" min="0" max="100" step="5" x-model.number="like" class="mt-3 w-full accent-brand-600">
                                <input type="hidden" name="preselection[rules][like_weight]" :value="like">
                                <input type="hidden" name="preselection[rules][jury_weight]" :value="100 - like">
                            </div>
                            <div class="flex flex-wrap gap-5">
                                @foreach (MediaType::cases() as $mediaType)
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="preselection[rules][media_types][]" value="{{ $mediaType->value }}" checked class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $mediaType->label() }}</label>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <x-ui.input name="preselection[rules][media_max_duration]" type="number" min="10" label="Durée max." :value="$pre->mediaMaxDuration" suffix="sec" />
                                <x-ui.input name="preselection[rules][media_max_size_mb]" type="number" min="1" label="Taille max." :value="$pre->mediaMaxSizeMb" suffix="Mo" />
                            </div>
                        </div>
                    </fieldset>
                </section>

                {{-- 3. Format --}}
                <section x-ref="step3" data-step="3" x-show="step === 3" @if ($start !== 3) x-cloak @endif class="space-y-6">
                    <div class="{{ $card }} space-y-6">
                        <div>
                            <h2 class="font-display text-xl font-bold">Format</h2>
                            <p class="mt-1 text-sm text-slate-500">La première phase génère la suite jusqu'à la finale, avec le calendrier prévu de chaque tour.</p>
                        </div>
                        <p x-show="withPre" class="flex gap-2 rounded-2xl bg-brand-50 p-4 text-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
                            <x-ui.icon name="information-circle" variant="m" class="size-5 shrink-0" />
                            <span>La compétition démarre après la présélection (délibération comprise), avec les <strong x-text="selection"></strong> artistes retenus.</span>
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                'poules' => ['squares-2x2', 'Poules puis phase finale', 'Chaque artiste se présente dans sa poule, les meilleurs vont en phase finale.'],
                                'elimination' => ['trophy', 'Élimination directe', 'Battles à 1 contre 1 dès le départ.'],
                                'double_elimination' => ['arrow-path-rounded-square', 'Double élimination', 'Éliminé après deux défaites.'],
                                'plus_tard' => ['clock', 'Je choisirai plus tard', 'Dans l\'onglet Phases & matchs.'],
                            ] as $value => [$icon, $label, $hint])
                                <label>
                                    <input type="radio" name="phase[type]" value="{{ $value }}" x-model="type" class="peer sr-only">
                                    <span class="{{ $choice }} !p-5">
                                        <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-ui.icon :name="$icon" class="size-5" /></span>
                                        <span class="mt-2 text-base font-semibold">{{ $label }}</span>
                                        <span class="text-sm text-slate-500">{{ $hint }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('phase.type')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <fieldset x-show="type !== 'plus_tard'" x-collapse x-bind:disabled="type === 'plus_tard'" class="space-y-6">
                        <div x-show="type === 'poules'" x-collapse>
                            <div x-data="groupPlanner({ entrants: {{ (int) old('phase.rules.expected_entrants') ?: 'null' }}, groups: {{ (int) old('phase.rules.group_count', 2) }}, qualifiers: {{ (int) old('phase.qualifiers_per_group', 2) }} })"
                                x-effect="sync(expected); groupsCount = g; qualsCount = q" class="{{ $card }} space-y-5">
                                <h3 class="font-display text-lg font-bold">Les poules</h3>
                                <x-ui.input name="phase[rules][expected_entrants]" type="number" min="2" max="1024" label="Participants attendus" x-model.number="n" x-bind:disabled="type !== 'poules'" icon="users"
                                    hint="Artistes retenus à la présélection, sinon le maximum d'inscrits." />
                                <div x-show="suggestions.length" class="space-y-2">
                                    <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Formules conseillées</p>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <template x-for="option in suggestions" :key="option.g + '-' + option.q">
                                            <button type="button" x-on:click="apply(option)" class="rounded-2xl bg-white p-3 text-left text-sm ring-1 ring-slate-200 transition hover:ring-brand-400 dark:bg-white/5 dark:ring-white/10"
                                                x-bind:class="g === option.g && q === option.q && '!ring-2 !ring-brand-500 !bg-brand-50 dark:!bg-brand-500/10'">
                                                <span class="block font-semibold text-slate-900 dark:text-white" x-text="option.label"></span>
                                                <span class="block text-xs text-slate-500" x-text="'Puis ' + option.next"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-3">
                                    <x-ui.input name="phase[rules][group_count]" type="number" min="1" label="Nombre de poules" x-model.number="g" />
                                    <x-ui.input name="phase[qualifiers_per_group]" type="number" min="1" label="Qualifiés / poule" x-model.number="q" />
                                    <x-ui.select name="phase[rules][draw_method]" label="Répartition" :options="GroupDrawMethod::options()" />
                                </div>
                                <p x-show="problem" class="flex gap-2 rounded-2xl bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-200"><x-ui.icon name="exclamation-triangle" variant="m" class="size-5 shrink-0" /><span x-text="problem"></span></p>
                            </div>
                        </div>

                        <div x-show="type === 'elimination' || type === 'double_elimination'" x-collapse>
                            <div class="{{ $card }}">
                                <x-ui.input name="phase[rules][expected_entrants]" type="number" min="2" max="1024" label="Participants attendus" icon="users" x-bind:value="expected" x-bind:disabled="type === 'poules' || type === 'plus_tard'"
                                    hint="Sert à prévoir les tours jusqu'à la finale et leur calendrier." />
                            </div>
                        </div>

                        <div class="{{ $card }} space-y-5">
                            <h3 class="font-display text-lg font-bold">Qui décide ?</h3>
                            <div x-show="mode === 'mixte'" x-collapse>
                                <x-ui.select name="phase[mode]" label="Mode de cette phase" x-bind:disabled="mode !== 'mixte'"
                                    :options="collect(CompetitionMode::options())->except(CompetitionMode::Hybrid->value)->all()" hint="Compétition mixte : par exemple les poules en ligne, la finale sur scène." />
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach (VoteMode::cases() as $voteMode)
                                    <label>
                                        <input type="radio" name="phase[rules][vote_mode]" value="{{ $voteMode->value }}" x-model="vote" class="peer sr-only">
                                        <span class="{{ $choice }} items-center text-center text-sm font-semibold">{{ $voteMode->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div x-show="vote === 'mixte'" x-collapse class="rounded-2xl bg-slate-50 p-5 dark:bg-white/[0.03]">
                                <div class="flex justify-between text-sm font-semibold">
                                    <span class="text-brand-700 dark:text-brand-300">Jury <span x-text="jury"></span> %</span>
                                    <span class="text-fuchsia-600 dark:text-fuchsia-300">Public <span x-text="100 - jury"></span> %</span>
                                </div>
                                <input type="range" min="5" max="95" step="5" x-model.number="jury" class="mt-3 w-full accent-brand-600">
                                <input type="hidden" name="phase[rules][jury_weight]" :value="jury">
                                <input type="hidden" name="phase[rules][public_weight]" :value="100 - jury">
                            </div>
                            <p class="text-xs text-slate-500">Médias, passages, départage et calendrier de chaque tour se règlent ensuite dans l'onglet Phases & matchs.</p>
                        </div>
                    </fieldset>
                </section>

                {{-- Actions --}}
                <div class="sticky bottom-0 z-10 -mx-4 flex items-center gap-3 border-t border-slate-200/70 bg-slate-50/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:px-5 dark:border-white/10 dark:bg-slate-950/90">
                    <x-ui.button variant="secondary" icon="arrow-left" x-show="step > 1" x-cloak x-on:click="go(step - 1)">Retour</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('organizers.competitions.index', $organizer)" x-show="step === 1">Annuler</x-ui.button>
                    <span class="ml-auto hidden text-xs text-slate-500 sm:block" x-text="'Étape ' + step + ' sur 3'"></span>
                    <x-ui.button variant="primary" size="lg" icon-right="arrow-right" x-show="step < 3" x-on:click="go(step + 1)" class="ml-auto sm:ml-0">Continuer</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="lg" icon="sparkles" x-show="step === 3" x-cloak class="ml-auto sm:ml-0">Créer la compétition</x-ui.button>
                </div>
            </div>

            {{-- Live summary --}}
            <aside class="lg:sticky lg:top-24">
                <div class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-lift">
                    <div class="border-b border-white/10 p-6">
                        <p class="text-[11px] font-semibold tracking-wider text-white/50 uppercase">Aperçu</p>
                        <p class="mt-1 font-display text-xl leading-tight font-bold" x-text="name || 'Votre compétition'"></p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-white/10 px-2.5 py-1" x-text="{ presentiel: 'Présentiel', en_ligne: 'En ligne', mixte: 'Mixte' }[mode]"></span>
                            <span class="rounded-full bg-white/10 px-2.5 py-1" x-text="fee ? fee.toLocaleString('fr-FR') + ' XOF' : 'Gratuit'"></span>
                            <span class="rounded-full bg-white/10 px-2.5 py-1" x-show="max" x-text="max + ' inscrits max.'"></span>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="mb-4 text-[11px] font-semibold tracking-wider text-white/50 uppercase">Jusqu'à la finale</p>
                        <ol class="space-y-0">
                            <template x-for="(item, index) in structure" :key="index + item.title">
                                <li class="relative flex gap-3 pb-4 last:pb-0">
                                    <span x-show="index < structure.length - 1" class="absolute top-7 left-3.5 h-[calc(100%-1.75rem)] w-px bg-white/15"></span>
                                    <span class="relative grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold"
                                        x-bind:class="index === structure.length - 1 ? 'bg-amber-400 text-slate-950' : 'bg-white/10 text-white'" x-text="index + 1"></span>
                                    <span class="min-w-0 pt-0.5">
                                        <span class="block text-sm font-semibold" x-text="item.title"></span>
                                        <span class="block text-xs text-white/60" x-show="item.detail" x-text="item.detail"></span>
                                    </span>
                                </li>
                            </template>
                        </ol>
                        <p x-show="withPre && preEnd" class="mt-5 rounded-2xl bg-white/5 p-3 text-xs text-white/70">
                            Envoi des prestations jusqu'au <span x-text="date(preEnd)"></span>.
                        </p>
                    </div>
                </div>
                <p class="mt-4 px-2 text-xs text-slate-500">Présentation, récompenses, règlement, jury et dates de chaque tour se complètent après la création.</p>
            </aside>
        </div>
    </form>
</x-layouts.app>
