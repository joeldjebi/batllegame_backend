{{-- Country fields (create and edit). --}}
@php $c = $country ?? null; @endphp
<div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
    <x-ui.input name="name" label="Nom" :value="$c?->name" required placeholder="Côte d'Ivoire" />
    <x-ui.input name="flag" label="Drapeau" :value="$c?->flag" placeholder="🇨🇮" />
</div>
<div class="grid grid-cols-3 gap-4">
    <x-ui.input name="iso2" label="ISO 2" :value="$c?->iso2" required maxlength="2" placeholder="CI" class="[&_input]:uppercase" />
    <x-ui.input name="iso3" label="ISO 3" :value="$c?->iso3" required maxlength="3" placeholder="CIV" class="[&_input]:uppercase" />
    <x-ui.input name="currency_code" label="Devise" :value="$c?->currency_code" maxlength="3" placeholder="XOF" class="[&_input]:uppercase" />
</div>
<div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    <x-ui.input name="dial_code" label="Indicatif" :value="$c?->dial_code" required placeholder="+225" />
    <x-ui.input name="phone_min_length" type="number" min="4" max="15" label="Chiffres min." :value="$c?->phone_min_length ?? 8" required />
    <x-ui.input name="phone_max_length" type="number" min="4" max="15" label="Chiffres max." :value="$c?->phone_max_length ?? 10" required />
    <x-ui.input name="position" type="number" min="0" label="Ordre" :value="$c?->position ?? 0" />
</div>
<x-ui.input name="phone_example" label="Exemple de numéro" :value="$c?->phone_example" placeholder="0701020304" hint="Numéro national sans indicatif, affiché dans les champs téléphone." />
<x-ui.toggle name="is_active" label="Actif" description="Proposé dans les champs téléphone et les listes de lieux." :checked="$c?->is_active ?? true" class="!px-0" />
