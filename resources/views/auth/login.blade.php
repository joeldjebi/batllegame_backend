<x-layouts.auth title="Connexion">
    <h1 class="mt-10 font-display text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Bon retour 👋</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Connectez-vous à l'espace organisateur pour piloter vos compétitions.</p>

    @if (session('status'))
        <div class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input name="email" type="email" label="Email" icon="envelope" placeholder="vous@exemple.com" required autofocus autocomplete="username" />
        <x-ui.input name="password" type="password" label="Mot de passe" icon="lock-closed" placeholder="••••••••" required autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-white/20 dark:bg-white/5">
            Rester connecté
        </label>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-full" icon-right="arrow-right">Se connecter</x-ui.button>
    </form>

    @if (config('organizers.self_signup'))
        <p class="mt-8 text-center text-sm text-slate-500">Pas encore d'espace ? <a href="{{ route('organizers.signup') }}" class="font-semibold text-brand-600 hover:text-brand-500 dark:text-brand-300">Créer mon espace organisateur</a></p>
    @endif

    <p class="mt-10 text-xs text-slate-400">Participants et public : connectez-vous depuis l'application mobile Battle Game.</p>

    <x-slot:aside>
        <x-ui.badge tone="gray" class="self-start !bg-white/15 !text-white !ring-white/25" :dot="false" icon="bolt">Rap · Chant · Freestyle</x-ui.badge>

        <div class="max-w-lg">
            <h2 class="font-display text-4xl leading-tight font-bold xl:text-5xl">Orchestrez vos battles comme un pro.</h2>
            <p class="mt-4 text-lg text-white/80">Inscriptions, poules, brackets, jury et vote du public en temps réel, réunis dans un seul back-office.</p>

            <dl class="mt-10 grid grid-cols-3 gap-4">
                @foreach ([['squares-2x2', 'Poules & brackets', 'générés automatiquement'], ['scale', 'Jury + public', 'scores pondérés'], ['shield-check', 'Votes fiables', 'numéro vérifié']] as [$icon, $label, $hint])
                    <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur">
                        <x-ui.icon :name="$icon" class="size-6" />
                        <dt class="mt-3 text-sm font-semibold">{{ $label }}</dt>
                        <dd class="text-xs text-white/70">{{ $hint }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <p class="text-sm text-white/60">© {{ date('Y') }} Battle Game</p>
    </x-slot:aside>
</x-layouts.auth>
