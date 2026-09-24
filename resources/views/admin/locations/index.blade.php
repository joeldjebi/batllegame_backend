@php
    // Edit / delete dialogs of a city or a commune, filled by the clicked row.
    $editing = old('_form') === 'edit-place'
        ? ['action' => old('_action'), 'kind' => old('_kind'), 'name' => old('name'), 'position' => old('position', 0), 'active' => (bool) old('is_active', true)]
        : ['action' => '', 'kind' => '', 'name' => '', 'position' => 0, 'active' => true];
    $place = fn (string $kind, $model, string $update, string $destroy, int $used) => [
        'action' => $update, 'destroy' => $destroy, 'kind' => $kind, 'name' => $model->name,
        'position' => $model->position, 'active' => $model->is_active, 'used' => $used,
    ];
    $citiesExample = "Abidjan\nBouaké\nYamoussoukro";
    $communesExample = "Cocody\nYopougon\nPlateau";
@endphp

<x-layouts.app title="Pays, villes, communes">
    <x-ui.page-header title="Pays, villes, communes" :breadcrumbs="['Console' => route('admin.dashboard'), 'Référentiel' => null]"
        description="Les lieux proposés dans les formulaires des organisateurs, des compétitions et des utilisateurs : personne ne les saisit à la main.">
        <x-slot:actions>
            <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-country')">Nouveau pays</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div x-data="{ edit: @js($editing), remove: { action: '', name: '', kind: '' } }" class="grid items-start gap-6 lg:grid-cols-[17rem_minmax(0,1fr)] xl:grid-cols-[17rem_minmax(0,1fr)_minmax(0,1fr)]">

        {{-- Countries --}}
        <x-ui.card title="Pays" icon="globe-europe-africa" :padding="false">
            <ul class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto dark:divide-white/5">
                @foreach ($countries as $item)
                    <li>
                        <a href="{{ route('admin.locations.index', ['country' => $item->id]) }}" @class([
                            'flex items-center gap-3 px-4 py-3 transition',
                            'bg-brand-50 dark:bg-brand-500/10' => $country?->is($item),
                            'hover:bg-slate-50 dark:hover:bg-white/[0.03]' => ! $country?->is($item),
                        ])>
                            <span class="text-xl leading-none">{{ $item->flag ?: '🏳️' }}</span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm font-semibold', 'text-brand-700 dark:text-brand-200' => $country?->is($item), 'text-slate-900 dark:text-white' => ! $country?->is($item)])>{{ $item->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $item->dial_code }} · {{ $item->cities_count }} ville(s)</span>
                            </span>
                            @unless ($item->is_active)<x-ui.badge tone="gray">Inactif</x-ui.badge>@endunless
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        {{-- Cities of the selected country --}}
        @if ($country)
            <x-ui.card :padding="false">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/5">
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-2 font-display text-lg font-bold">
                            <span>{{ $country->flag }}</span> <span class="truncate">{{ $country->name }}</span>
                            @unless ($country->is_active)<x-ui.badge tone="gray">Inactif</x-ui.badge>@endunless
                        </h2>
                        <p class="text-xs text-slate-500">{{ $country->dial_code }} · {{ $country->phone_min_length === $country->phone_max_length ? $country->phone_min_length : $country->phone_min_length.'–'.$country->phone_max_length }} chiffres · {{ $country->currency_code ?? 'sans devise' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button size="sm" variant="secondary" icon="pencil-square" x-on:click="$dispatch('open-modal', 'edit-country')">Modifier</x-ui.button>
                        <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'add-cities')">Villes</x-ui.button>
                    </div>
                </header>

                <div class="border-b border-slate-100 px-5 py-3 dark:border-white/5">
                    <form method="GET" class="relative">
                        <input type="hidden" name="country" value="{{ $country->id }}">
                        <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher une ville…" class="w-full rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                    </form>
                </div>

                @if ($cities->isEmpty())
                    <div class="p-5">
                        <x-ui.empty icon="map" title="{{ request('q') ? 'Aucune ville trouvée' : 'Aucune ville' }}" description="Ajoutez les villes de ce pays : une ou plusieurs à la fois." />
                    </div>
                @else
                    <ul class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto dark:divide-white/5">
                        @foreach ($cities as $item)
                            @php $used = $usage['cities'][$item->id] ?? 0; @endphp
                            <li @class(['group flex items-center gap-2 pr-3', 'bg-brand-50 dark:bg-brand-500/10' => $city?->is($item)])>
                                <a href="{{ route('admin.locations.index', array_filter(['country' => $country->id, 'city' => $item->id, 'q' => request('q')])) }}" class="flex min-w-0 flex-1 items-center gap-3 px-5 py-3">
                                    <span @class(['min-w-0 flex-1', 'opacity-50' => ! $item->is_active])>
                                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $item->name }}</span>
                                        <span class="block text-xs text-slate-500">{{ $item->communes_count }} commune(s){{ $used ? ' · utilisée '.$used.' fois' : '' }}</span>
                                    </span>
                                    @unless ($item->is_active)<x-ui.badge tone="gray">Inactive</x-ui.badge>@endunless
                                    <x-ui.icon name="chevron-right" variant="m" class="size-4 text-slate-300" />
                                </a>
                                <button type="button" title="Modifier" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-white hover:text-brand-600 dark:hover:bg-white/10"
                                    x-on:click="edit = @js($place('ville', $item, route('admin.locations.cities.update', $item), route('admin.locations.cities.destroy', $item), $used)); $dispatch('open-modal', 'edit-place')">
                                    <x-ui.icon name="pencil-square" variant="m" class="size-4" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- Communes of the selected city --}}
            <x-ui.card :padding="false" class="lg:col-start-2 xl:col-start-auto">
                @if ($city)
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/5">
                        <div class="min-w-0">
                            <h2 class="truncate font-display text-lg font-bold">Communes · {{ $city->name }}</h2>
                            <p class="text-xs text-slate-500">Facultatif : une ville sans commune se choisit seule. Dès qu'une commune existe, elle est demandée.</p>
                        </div>
                        <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'add-communes')">Communes</x-ui.button>
                    </header>
                    @if ($communes->isEmpty())
                        <div class="p-5"><x-ui.empty icon="map-pin" title="Aucune commune" description="Ajoutez les communes (ou quartiers) de {{ $city->name }}." /></div>
                    @else
                        <ul class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto dark:divide-white/5">
                            @foreach ($communes as $item)
                                @php $used = $usage['communes'][$item->id] ?? 0; @endphp
                                <li class="flex items-center gap-3 py-3 pr-3 pl-5">
                                    <span @class(['min-w-0 flex-1', 'opacity-50' => ! $item->is_active])>
                                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $item->name }}</span>
                                        @if ($used)<span class="block text-xs text-slate-500">Utilisée {{ $used }} fois</span>@endif
                                    </span>
                                    @unless ($item->is_active)<x-ui.badge tone="gray">Inactive</x-ui.badge>@endunless
                                    <button type="button" title="Modifier" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-brand-600 dark:hover:bg-white/10"
                                        x-on:click="edit = @js($place('commune', $item, route('admin.locations.communes.update', $item), route('admin.locations.communes.destroy', $item), $used)); $dispatch('open-modal', 'edit-place')">
                                        <x-ui.icon name="pencil-square" variant="m" class="size-4" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="p-5"><x-ui.empty icon="map-pin" title="Choisissez une ville" description="Ses communes s'affichent ici." /></div>
                @endif
            </x-ui.card>
        @else
            <x-ui.card class="xl:col-span-2"><x-ui.empty icon="globe-europe-africa" title="Aucun pays" description="Créez un premier pays pour y ajouter des villes." /></x-ui.card>
        @endif

        {{-- Edit a city / commune (one dialog, filled by the row) --}}
        <x-ui.modal name="edit-place" icon="pencil-square" title="Modifier">
            <form method="POST" x-bind:action="edit.action" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="_form" value="edit-place">
                <input type="hidden" name="_action" x-bind:value="edit.action">
                <input type="hidden" name="_kind" x-bind:value="edit.kind">
                <p class="text-sm text-slate-500" x-text="edit.kind === 'ville' ? 'Ville' : 'Commune'"></p>
                <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                    <x-ui.input name="name" label="Nom" required x-model="edit.name" />
                    <x-ui.input name="position" type="number" min="0" label="Ordre" x-model="edit.position" />
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="is_active" x-bind:value="edit.active ? 1 : 0">
                    <input type="checkbox" x-model="edit.active" class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span><span class="block font-medium text-slate-800 dark:text-slate-100">Active</span><span class="block text-xs text-slate-500">Inactive : n'est plus proposée, reste affichée sur les fiches qui l'utilisent.</span></span>
                </label>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center dark:border-white/5">
                    <x-ui.button variant="danger-soft" icon="trash" class="sm:mr-auto" x-bind:disabled="edit.used > 0" x-bind:title="edit.used > 0 ? 'Utilisée : désactivez-la plutôt' : ''"
                        x-on:click="remove = { action: edit.destroy, name: edit.name, kind: edit.kind }; $dispatch('close-modal', 'edit-place'); $dispatch('open-modal', 'remove-place')">Supprimer</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'edit-place')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="check">Enregistrer</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="remove-place" icon="trash" danger max-width="md" title="Supprimer ?" description="Suppression définitive (avec ses communes pour une ville). Possible seulement si personne ne l'utilise.">
            <form method="POST" x-bind:action="remove.action" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                @csrf @method('DELETE')
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'remove-place')">Annuler</x-ui.button>
                <x-ui.button type="submit" variant="danger" icon="trash"><span x-text="'Supprimer « ' + remove.name + ' »'"></span></x-ui.button>
            </form>
        </x-ui.modal>
    </div>

    @push('modals')
        <x-ui.modal name="create-country" icon="globe-europe-africa" title="Nouveau pays" max-width="xl">
            <form method="POST" action="{{ route('admin.locations.countries.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="create-country">
                @include('admin.locations.country-form', ['country' => null])
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'create-country')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="check">Créer</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($country)
            <x-ui.modal name="edit-country" icon="pencil-square" :title="'Modifier · '.$country->name" max-width="xl">
                <form method="POST" action="{{ route('admin.locations.countries.update', $country) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="_form" value="edit-country">
                    @include('admin.locations.country-form', ['country' => $country])
                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:items-center sm:justify-end">
                        @unless ($country->cities_count || $country->users()->exists())
                            <x-ui.button variant="danger-soft" icon="trash" class="sm:mr-auto" x-on:click="$dispatch('close-modal', 'edit-country'); $dispatch('open-modal', 'remove-country')">Supprimer</x-ui.button>
                        @endunless
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'edit-country')">Annuler</x-ui.button>
                        <x-ui.button type="submit" icon="check">Enregistrer</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            <x-ui.modal name="remove-country" icon="trash" danger max-width="md" :title="'Supprimer '.$country->name.' ?'" description="Aucune ville ni aucun compte ne l'utilise : il sera supprimé définitivement.">
                <form method="POST" action="{{ route('admin.locations.countries.destroy', $country) }}" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    @csrf @method('DELETE')
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'remove-country')">Annuler</x-ui.button>
                    <x-ui.button type="submit" variant="danger" icon="trash">Supprimer</x-ui.button>
                </form>
            </x-ui.modal>

            <x-ui.modal name="add-cities" icon="map" :title="'Ajouter des villes · '.$country->name" description="Une ville par ligne. Les doublons et les villes déjà présentes sont ignorés.">
                <form method="POST" action="{{ route('admin.locations.cities.store', $country) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="add-cities">
                    <x-ui.textarea name="cities" label="Villes" rows="6" required :placeholder="$citiesExample" />
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'add-cities')">Annuler</x-ui.button>
                        <x-ui.button type="submit" icon="plus">Ajouter</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif

        @if ($city)
            <x-ui.modal name="add-communes" icon="map-pin" :title="'Ajouter des communes · '.$city->name" description="Une commune par ligne. Les doublons et les communes déjà présentes sont ignorés.">
                <form method="POST" action="{{ route('admin.locations.communes.store', $city) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="add-communes">
                    <x-ui.textarea name="communes" label="Communes" rows="6" required :placeholder="$communesExample" />
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'add-communes')">Annuler</x-ui.button>
                        <x-ui.button type="submit" icon="plus">Ajouter</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    @endpush
</x-layouts.app>
