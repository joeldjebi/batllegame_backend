@props(['countries' => \App\Models\Country::query()->active()->get()])
<label>Indicatif
    <select name="country_id" required>
        @foreach ($countries as $country)
            <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->flag }} {{ $country->dial_code }}</option>
        @endforeach
    </select>
</label>
<label>Téléphone
    <input name="phone" value="{{ old('phone') }}" placeholder="{{ $countries->first()?->phone_example }}" required>
</label>
