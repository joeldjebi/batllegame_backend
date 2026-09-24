@props([
    'city' => null,
    'commune' => null,
    'required' => false,
    'label' => 'Ville',
    'communeLabel' => 'Commune',
    'hint' => null,
])

{{-- Country > city > commune picked from the super-admin's lists (App\Support\Locations).
     Posts city_id and commune_id; the country select only shows with several countries. --}}
@php
    $tree = \App\Support\Locations::tree();
    $cityId = (int) old('city_id', $city) ?: null;
    $communeId = (int) old('commune_id', $commune) ?: null;
    $countryId = collect($tree)->first(fn ($c) => collect($c['cities'])->contains('id', $cityId))['id'] ?? ($tree[0]['id'] ?? null);
    $error = $errors->first('city_id') ?: $errors->first('commune_id');
    $select = 'block w-full rounded-xl border-0 bg-white py-2.5 pr-9 pl-3 text-sm text-slate-900 shadow-soft ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand-500 dark:bg-white/5 dark:text-white dark:[&_option]:bg-slate-900 '
        .($error ? 'ring-rose-300' : 'ring-slate-200 dark:ring-white/10');
@endphp

<div x-data="{
        tree: @js($tree),
        country: @js($countryId),
        city: @js($cityId),
        commune: @js($communeId),
        get cities() { return this.tree.find((c) => c.id === this.country)?.cities ?? [] },
        get communes() { return this.cities.find((c) => c.id === this.city)?.communes ?? [] },
    }"
    x-effect="if (city && ! cities.some((c) => c.id === city)) city = null; if (commune && ! communes.some((c) => c.id === commune)) commune = null"
    {{ $attributes->class('space-y-4') }}>
    @if (count($tree) === 0)
        <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-white/5">Aucune ville n'est encore proposée par Battle Game.</p>
    @else
        <div @class(['grid gap-4', 'sm:grid-cols-2' => count($tree) > 1])>
            @if (count($tree) > 1)
                <x-ui.field label="Pays" :required="$required">
                    <select x-model.number="country" class="{{ $select }}">
                        @foreach ($tree as $item)<option value="{{ $item['id'] }}">{{ $item['flag'] }} {{ $item['name'] }}</option>@endforeach
                    </select>
                </x-ui.field>
            @endif
            <x-ui.field :label="$label" :required="$required" :error="$errors->first('city_id')" :hint="$errors->has('city_id') ? null : $hint">
                <select name="city_id" x-model.number="city" @required($required) class="{{ $select }}">
                    <option value="">{{ $required ? 'Choisir une ville' : 'Non précisée' }}</option>
                    <template x-for="item in cities" :key="item.id">
                        <option :value="item.id" x-text="item.name" :selected="item.id === city"></option>
                    </template>
                </select>
            </x-ui.field>
        </div>

        <div x-show="communes.length" x-cloak>
            <x-ui.field :label="$communeLabel" :required="$required" :error="$errors->first('commune_id')">
                <select name="commune_id" x-model.number="commune" x-bind:required="@js($required) && communes.length > 0" x-bind:disabled="! communes.length" class="{{ $select }}">
                    <option value="">{{ $required ? 'Choisir une commune' : 'Non précisée' }}</option>
                    <template x-for="item in communes" :key="item.id">
                        <option :value="item.id" x-text="item.name" :selected="item.id === commune"></option>
                    </template>
                </select>
            </x-ui.field>
        </div>
    @endif
</div>
