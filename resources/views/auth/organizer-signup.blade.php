@php
    // Steps of the wizard: one screen each, so the page never scrolls.
    $steps = $user
        ? [['building-office-2', 'Votre organisateur'], ['check-badge', 'Confirmation']]
        : [['building-office-2', 'Votre organisateur'], ['user', 'Vous'], ['lock-closed', 'Sécurité']];
    $fieldSteps = $user
        ? ['organizer_name' => 1, 'city_id' => 1, 'commune_id' => 1, 'terms' => 2]
        : ['organizer_name' => 1, 'city_id' => 1, 'commune_id' => 1, 'name' => 2, 'email' => 2, 'country_id' => 2, 'phone' => 2, 'password' => 3, 'terms' => 3];
    // Back on the step of the first error.
    $start = collect($fieldSteps)->first(fn ($step, $field) => $errors->has($field)) ?? 1;
    $compact = '[@media(max-height:700px)]:hidden';
@endphp

<x-layouts.auth title="Créer mon espace organisateur" wide fit>
    <form method="POST" action="{{ route('organizers.signup') }}" novalidate
        x-data="{
            step: {{ $start }},
            last: {{ count($steps) }},
            next() {
                const invalid = [...this.$refs['step' + this.step].querySelectorAll('input, select, textarea')].find((field) => ! field.checkValidity());
                if (invalid) return invalid.reportValidity();
                this.step++;
                this.$nextTick(() => this.$refs['step' + this.step].querySelector('input:not([type=hidden]), select')?.focus());
            },
        }"
        x-on:keydown.enter="if (step < last && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); next() }"
        x-on:submit="const invalid = [...$el.querySelectorAll('input, select')].find((field) => ! field.checkValidity()); if (invalid) { $event.preventDefault(); step = Number(invalid.closest('[data-step]').dataset.step); $nextTick(() => invalid.reportValidity()) }"
        class="mt-6 flex min-h-0 flex-1 flex-col sm:mt-10 [@media(max-height:700px)]:mt-4 [@media(min-height:820px)]:my-auto [@media(min-height:820px)]:flex-none">
        @csrf

        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Créez votre espace organisateur</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400 {{ $compact }}">Gratuit et immédiat. Battle Game vérifie votre organisateur avant l'ouverture des inscriptions.</p>

            {{-- Progress --}}
            <div class="mt-5 [@media(max-height:700px)]:mt-3">
                <div class="flex gap-1.5">
                    @foreach ($steps as $i => $item)
                        <span class="h-1.5 flex-1 rounded-full transition-colors" x-bind:class="step >= {{ $i + 1 }} ? 'bg-brand-600' : 'bg-slate-200 dark:bg-white/10'"></span>
                    @endforeach
                </div>
                <p class="mt-2 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                    @foreach ($steps as $i => [$icon, $label])
                        <span x-show="step === {{ $i + 1 }}" @if ($i + 1 !== $start) x-cloak @endif class="inline-flex items-center gap-1.5">
                            <x-ui.icon :name="$icon" variant="m" class="size-4 text-brand-600" /> Étape {{ $i + 1 }} sur {{ count($steps) }} · {{ $label }}
                        </span>
                    @endforeach
                </p>
            </div>
        </div>

        {{-- Current step: fills the free height (scrolls inside only on very small screens) --}}
        <div class="-mx-1 mt-5 min-h-0 flex-1 overflow-y-auto px-1 pb-1 [@media(max-height:700px)]:mt-3 [@media(min-height:820px)]:min-h-72 [@media(min-height:820px)]:flex-none">
            <div x-ref="step1" data-step="1" x-show="step === 1" @if ($start !== 1) x-cloak @endif class="space-y-4">
                <x-ui.input name="organizer_name" label="Nom de l'organisateur" icon="building-office-2" placeholder="Ex. Yop City Battle" required autofocus />
                <x-location-select :required="\App\Support\Locations::tree() !== []" />
            </div>

            @if ($user)
                <div x-ref="step2" data-step="2" x-show="step === 2" @if ($start !== 2) x-cloak @endif class="space-y-4">
                    <div class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-900/5 dark:bg-white/5 dark:ring-white/10">
                        <x-ui.avatar :name="$user->name" size="sm" />
                        <p class="min-w-0 flex-1 text-sm">
                            <span class="block truncate font-semibold">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-slate-500">Vous serez propriétaire de ce nouvel organisateur.</span>
                        </p>
                    </div>
                    @include('auth.partials.signup-terms')
                </div>
            @else
                <div x-ref="step2" data-step="2" x-show="step === 2" @if ($start !== 2) x-cloak @endif class="space-y-4">
                    <x-ui.input name="name" label="Nom complet" icon="user" required autocomplete="name" />
                    <x-ui.input name="email" type="email" label="Email de connexion" icon="envelope" placeholder="vous@exemple.com" required autocomplete="email" />
                    <x-phone-input :countries="$countries" />
                </div>

                <div x-ref="step3" data-step="3" x-show="step === 3" @if ($start !== 3) x-cloak @endif class="space-y-4">
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <x-ui.input name="password" type="password" label="Mot de passe" icon="lock-closed" required minlength="8" autocomplete="new-password" />
                        <x-ui.input name="password_confirmation" type="password" label="Confirmation" icon="lock-closed" required minlength="8" autocomplete="new-password" />
                    </div>
                    <p class="-mt-2 text-xs text-slate-500">8 caractères minimum, avec des lettres et des chiffres.</p>
                    <p class="flex gap-2 rounded-xl bg-brand-50 p-3 text-xs text-brand-800 dark:bg-brand-500/10 dark:text-brand-200 {{ $compact }}">
                        <x-ui.icon name="information-circle" variant="m" class="size-4 shrink-0" />
                        <span>Déjà un compte artiste ou public avec ce numéro ? Saisissez son mot de passe : le même compte servira au back-office.</span>
                    </p>
                    @include('auth.partials.signup-terms')
                </div>
            @endif
        </div>

        {{-- Actions, always visible --}}
        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-white/10">
            <div class="flex gap-3">
                <x-ui.button variant="secondary" size="lg" icon="arrow-left" x-show="step > 1" x-cloak x-on:click="step--" aria-label="Étape précédente" />
                <x-ui.button variant="primary" size="lg" class="flex-1" icon-right="arrow-right" x-show="step < last" x-on:click="next()">Continuer</x-ui.button>
                <x-ui.button type="submit" variant="primary" size="lg" class="flex-1" icon="check" x-show="step === last" x-cloak>Créer mon espace</x-ui.button>
            </div>
            @unless ($user)
                <p class="mt-3 text-center text-sm text-slate-500">Déjà un espace ? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">Se connecter</a></p>
            @endunless
        </div>
    </form>

    <x-slot:aside>
        <x-ui.badge tone="gray" class="self-start !bg-white/15 !text-white !ring-white/25" :dot="false" icon="rocket-launch">Espace organisateur</x-ui.badge>

        <div class="max-w-lg">
            <h2 class="font-display text-4xl leading-tight font-bold xl:text-5xl">Lancez votre battle en quelques minutes.</h2>
            <ol class="mt-10 space-y-4 [@media(max-height:760px)]:mt-6 [@media(max-height:760px)]:space-y-2">
                @foreach ([['building-office-2', 'Créez votre espace', 'Votre organisateur et votre compte, tout de suite.'], ['trophy', 'Préparez vos compétitions', 'Présentation, récompenses, phases, jury, présélection.'], ['check-badge', 'Vérification par Battle Game', 'Puis ouvrez les inscriptions et le vote du public.']] as $i => [$icon, $label, $hint])
                    <li class="flex gap-4 rounded-2xl bg-white/10 p-4 ring-1 ring-white/20">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15"><x-ui.icon :name="$icon" class="size-5" /></span>
                        <span>
                            <span class="block text-sm font-semibold">{{ $i + 1 }}. {{ $label }}</span>
                            <span class="block text-sm text-white/70">{{ $hint }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>

        <p class="text-sm text-white/60">© {{ date('Y') }} Battle Game</p>
    </x-slot:aside>
</x-layouts.auth>
