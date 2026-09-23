@props(['countries' => \App\Models\Country::query()->active()->get(), 'label' => 'Téléphone', 'required' => true])

@php($error = $errors->first('phone') ?: $errors->first('country_id'))

<x-ui.field :label="$label" :error="$error" {{ $attributes }}>
    <div @class([
        'flex rounded-xl bg-white shadow-soft ring-1 ring-inset focus-within:ring-2 focus-within:ring-brand-500 dark:bg-white/5',
        'ring-rose-300' => $error,
        'ring-slate-200 dark:ring-white/10' => ! $error,
    ])>
        <select name="country_id" @required($required) class="rounded-l-xl border-0 bg-transparent py-2.5 pr-8 pl-3 text-sm text-slate-700 focus:ring-0 dark:text-slate-200 dark:[&_option]:bg-slate-900">
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->flag }} {{ $country->dial_code }}</option>
            @endforeach
        </select>
        <span class="my-2 w-px bg-slate-200 dark:bg-white/10"></span>
        <input name="phone" value="{{ old('phone') }}" inputmode="tel" @required($required) placeholder="{{ $countries->first()?->phone_example }}"
            class="block w-full rounded-r-xl border-0 bg-transparent py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 dark:text-white">
    </div>
</x-ui.field>
