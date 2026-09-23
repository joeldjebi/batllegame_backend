@props(['name', 'label' => null, 'options' => [], 'value' => null, 'hint' => null, 'placeholder' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', $key).'-'.substr(md5(uniqid('', true)), 0, 5));
    $error = $errors->first($key);
    $selected = old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint" :required="$attributes->has('required')" :class="$attributes->get('class')">
    <select id="{{ $id }}" name="{{ $name }}"
        {{ $attributes->except('class')->class([
            'block w-full rounded-xl border-0 bg-white py-2.5 pr-10 pl-3 text-sm text-slate-900 shadow-soft ring-1 ring-inset transition focus:ring-2 focus:ring-inset dark:bg-white/5 dark:text-white dark:[&_option]:bg-slate-900',
            'ring-rose-300 focus:ring-rose-500' => $error,
            'ring-slate-200 focus:ring-brand-500 dark:ring-white/10' => ! $error,
        ]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-ui.field>
