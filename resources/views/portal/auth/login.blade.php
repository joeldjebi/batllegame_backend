<x-layouts.portal title="Connexion">
    <div class="mx-auto max-w-md">
        <x-ui.card :padding="false">
            <div class="border-b border-slate-100 px-6 py-6 text-center dark:border-white/5">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-600 text-white"><x-ui.icon :name="$portal->icon" class="size-7" /></span>
                <h1 class="mt-4 font-display text-2xl font-bold">{{ $portal->title }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $portal->tagline }}</p>
            </div>
            <form method="POST" action="{{ route($portal->key.'.login') }}" class="space-y-5 p-6">
                @csrf
                <x-phone-input :countries="$countries" />
                <x-ui.input name="password" type="password" label="Mot de passe" icon="lock-closed" required autocomplete="current-password" />
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Rester connecté
                </label>
                <x-ui.button type="submit" size="lg" class="w-full" icon-right="arrow-right">Se connecter</x-ui.button>
                @if ($portal->canRegister)
                    <p class="text-center text-sm text-slate-500">Pas encore de compte ? <a href="{{ route($portal->key.'.register') }}" class="font-semibold text-brand-600 hover:text-brand-500">Créer un compte</a></p>
                @else
                    <p class="text-center text-xs text-slate-400">Votre compte juré est créé par l'organisateur. Vos identifiants vous ont été envoyés par SMS.</p>
                @endif
            </form>
        </x-ui.card>
    </div>
</x-layouts.portal>
