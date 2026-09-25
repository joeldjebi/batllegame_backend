{{-- A performance is always shown with the artist's photo: required before any upload. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:ring-amber-400/20']) }}>
    <x-ui.icon name="user-circle" class="size-9 shrink-0 text-amber-600 dark:text-amber-300" />
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Ajoute ta photo de profil pour envoyer ta prestation</p>
        <p class="mt-0.5 text-sm text-amber-800/80 dark:text-amber-200/80">Elle te représente auprès du public et du jury, à côté de chaque prestation.</p>
    </div>
    <a href="{{ route('artist.profile.edit') }}" class="shrink-0 rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Ajouter ma photo</a>
</div>
