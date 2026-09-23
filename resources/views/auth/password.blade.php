<x-layouts.app title="Mot de passe">
    <div class="mx-auto max-w-lg">
        <x-ui.page-header title="Mot de passe" description="Choisissez un mot de passe personnel d'au moins 8 caractères." />
        @if ($forced)
            <div class="mb-6 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
                <x-ui.icon name="key" class="size-5 shrink-0" />
                <p>Votre compte a été créé avec un mot de passe provisoire : remplacez-le pour accéder au back-office.</p>
            </div>
        @endif
        <x-ui.card title="Changer mon mot de passe" icon="key">
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-ui.input name="current_password" type="password" :label="$forced ? 'Mot de passe provisoire' : 'Mot de passe actuel'" required autocomplete="current-password" />
                <x-ui.input name="password" type="password" label="Nouveau mot de passe" required autocomplete="new-password" />
                <x-ui.input name="password_confirmation" type="password" label="Confirmation" required autocomplete="new-password" />
                <x-ui.button type="submit" class="w-full" icon="check">Enregistrer</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
