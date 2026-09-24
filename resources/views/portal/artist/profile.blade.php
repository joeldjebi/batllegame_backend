<x-layouts.portal title="Mon profil">
    <x-ui.page-header title="Mon profil" :breadcrumbs="['Mon espace' => route('artist.dashboard'), 'Profil' => null]"
        description="Ta photo et ton nom apparaissent sur tes prestations, pour l'organisateur, le jury et le public." />

    <form method="POST" action="{{ route('artist.profile.update') }}" enctype="multipart/form-data" class="mx-auto max-w-2xl space-y-6"
        x-data="{ preview: @js($user->avatarUrl()), remove: false }">
        @csrf @method('PUT')

        <x-ui.card>
            <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
                <div class="relative">
                    <template x-if="preview">
                        <img :src="preview" alt="" class="size-28 rounded-full object-cover ring-4 ring-white shadow-lift dark:ring-slate-900">
                    </template>
                    <template x-if="! preview">
                        <x-ui.avatar :name="$user->name" size="xl" class="!size-28 !text-3xl ring-4 shadow-lift" />
                    </template>
                    <label class="absolute -right-1 -bottom-1 grid size-10 cursor-pointer place-items-center rounded-full bg-brand-600 text-white shadow-lift ring-4 ring-white transition hover:bg-brand-500 dark:ring-slate-900" title="Changer la photo">
                        <x-ui.icon name="camera" variant="m" class="size-5" />
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                            x-on:change="const file = $event.target.files[0]; if (file) { preview = URL.createObjectURL(file); remove = false }">
                    </label>
                </div>
                <div class="text-center sm:text-left">
                    <p class="font-display text-lg font-bold">Photo de profil</p>
                    <p class="text-sm text-slate-500">Un portrait net, de face. JPG, PNG ou WebP, 5 Mo max. Recadrée en carré automatiquement.</p>
                    <button type="button" x-show="preview" x-on:click="preview = null; remove = true; $root.querySelector('input[name=photo]').value = ''" class="mt-2 text-sm font-semibold text-rose-600 hover:text-rose-500">Retirer la photo</button>
                    <input type="hidden" name="remove_photo" :value="remove ? 1 : 0">
                    @error('photo')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Informations" icon="user">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="name" label="Nom complet" icon="user" :value="$user->name" required class="sm:col-span-2" />
                <x-ui.field label="Téléphone" hint="Ton identifiant de connexion : il ne se modifie pas ici.">
                    <p class="flex items-center gap-2 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10">{{ $user->country?->flag }} {{ $user->phone }}</p>
                </x-ui.field>
                <x-ui.input name="email" type="email" label="Email (facultatif)" icon="envelope" :value="$user->email" :disabled="$user->organizerMemberships()->exists()" />
                <x-location-select :city="$user->city_id" :commune="$user->commune_id" label="Ville" class="sm:col-span-2" />
            </div>
        </x-ui.card>

        <div class="flex justify-end">
            <x-ui.button type="submit" size="lg" icon="check">Enregistrer</x-ui.button>
        </div>
    </form>
</x-layouts.portal>
