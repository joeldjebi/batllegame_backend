<x-layouts.portal title="Vérification du numéro">
    <div class="mx-auto max-w-md">
        <x-ui.card title="Vérifiez votre numéro" icon="device-phone-mobile" description="Un code à 6 chiffres a été envoyé par SMS. La vérification garantit un vote par personne.">
            @if ($fixed = \App\Services\PhoneVerificationService::fixedCode())
                <div class="mb-4 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-ui.icon name="beaker" variant="m" class="size-4" /> Mode test : le code est <strong class="font-display tracking-widest">{{ $fixed }}</strong>
                </div>
            @endif
            <form method="POST" action="{{ route('fan.verification.verify') }}" class="space-y-4">
                @csrf
                <x-ui.input name="code" label="Code reçu par SMS" inputmode="numeric" maxlength="6" required autofocus class="[&_input]:text-center [&_input]:font-display [&_input]:text-2xl [&_input]:tracking-[0.5em]" />
                <x-ui.button type="submit" class="w-full" icon="check-badge">Vérifier</x-ui.button>
            </form>
            <x-slot:footer>
                <form method="POST" action="{{ route('fan.verification.send') }}" class="text-center">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-brand-600 hover:text-brand-500">Renvoyer un code</button>
                </form>
            </x-slot:footer>
        </x-ui.card>
    </div>
</x-layouts.portal>
