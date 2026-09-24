<x-layouts.portal title="Créer un compte">
    <div class="mx-auto max-w-md">
        <x-ui.card :padding="false">
            <div class="border-b border-slate-100 px-6 py-6 text-center dark:border-white/5">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-600 text-white"><x-ui.icon name="user-plus" class="size-7" /></span>
                <h1 class="mt-4 font-display text-2xl font-bold">Créer un compte</h1>
                <p class="mt-1 text-sm text-slate-500">Un seul compte pour voter et participer aux compétitions.</p>
            </div>
            <form method="POST" action="{{ route($portal->key.'.register') }}" class="space-y-5 p-6">
                @csrf
                <x-ui.input name="name" label="Nom complet" icon="user" required />
                <x-phone-input :countries="$countries" />
                <x-location-select label="Ville (facultatif)" communeLabel="Commune (facultatif)" hint="Pour vous proposer les compétitions près de chez vous." />
                <x-ui.input name="password" type="password" label="Mot de passe" icon="lock-closed" required autocomplete="new-password" hint="8 caractères minimum." />
                <x-ui.input name="password_confirmation" type="password" label="Confirmation" icon="lock-closed" required autocomplete="new-password" />
                <x-ui.button type="submit" size="lg" class="w-full" icon="check">Créer mon compte</x-ui.button>
                <p class="text-center text-sm text-slate-500">Déjà inscrit ? <a href="{{ route($portal->key.'.login') }}" class="font-semibold text-brand-600 hover:text-brand-500">Se connecter</a></p>
            </form>
        </x-ui.card>
    </div>
</x-layouts.portal>
