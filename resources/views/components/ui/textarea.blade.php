@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'rows' => 4])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f-'.str_replace('.', '-', $key).'-'.substr(md5(uniqid('', true)), 0, 5);
    $error = $errors->first($key);
@endphp

<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint" :class="$attributes->get('class')">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->except('class')->class([
            'block w-full rounded-xl border-0 bg-white py-2.5 text-sm text-slate-900 shadow-soft ring-1 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-inset dark:bg-white/5 dark:text-white',
            'ring-rose-300 focus:ring-rose-500' => $error,
            'ring-slate-200 focus:ring-brand-500 dark:ring-white/10' => ! $error,
        ]) }}>{{ old($key, $value) }}</textarea>
</x-ui.field>
