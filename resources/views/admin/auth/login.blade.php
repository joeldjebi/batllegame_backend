<x-layouts.auth title="Administration" admin>
    <div class="mt-10 inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300">
        <x-ui.icon name="lock-closed" variant="m" class="size-3.5" /> Accès restreint
    </div>
    <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Administration de la plateforme</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Accès réservé au super-administrateur. Toutes les tentatives sont journalisées.</p>

    <form method="POST" action="{{ route('admin.login') }}" class="mt-8 space-y-5">
        @csrf
        <x-ui.input name="email" type="email" label="Email" icon="envelope" required autofocus autocomplete="username" />
        <x-ui.input name="password" type="password" label="Mot de passe" icon="key" required autocomplete="current-password" />
        <x-ui.button type="submit" size="lg" class="w-full !bg-slate-900 hover:!bg-slate-800 dark:!bg-white dark:!text-slate-900" icon="shield-check">Accéder à la console</x-ui.button>
    </form>

    <x-slot:aside>
        <div class="flex items-center gap-2 text-sm font-medium text-emerald-400"><span class="size-2 animate-pulse rounded-full bg-emerald-400"></span> Connexion chiffrée</div>

        <div class="max-w-md">
            <x-ui.icon name="shield-check" class="size-14 text-brand-400" />
            <h2 class="mt-6 font-display text-4xl font-bold">Console de supervision</h2>
            <p class="mt-4 text-white/70">Vérification des organisateurs, suspension et contrôle global de la plateforme.</p>

            <ul class="mt-10 space-y-4 text-sm text-white/80">
                @foreach (['Session isolée des espaces organisateurs', 'Limitation des tentatives de connexion', 'Expiration après '.config('admin.idle_timeout').' min d\'inactivité', 'Journal des connexions réussies et échouées'] as $item)
                    <li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-emerald-500/15 text-emerald-400"><x-ui.icon name="check" variant="m" class="size-4" /></span>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <p class="text-sm text-white/40">Battle Game · Super-admin</p>
    </x-slot:aside>
</x-layouts.auth>
