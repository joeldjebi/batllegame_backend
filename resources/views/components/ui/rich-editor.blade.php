@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'placeholder' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f-'.str_replace('.', '-', $key).'-'.substr(md5(uniqid('', true)), 0, 5);
    $error = $errors->first($key);
@endphp

{{-- Trix editor (loaded on demand by app.js). Output is sanitized server-side (App\Support\RichText). --}}
<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint" :class="$attributes->get('class')">
    <input id="{{ $id }}-input" type="hidden" name="{{ $name }}" value="{{ old($key, $value) }}">
    <trix-editor id="{{ $id }}" input="{{ $id }}-input" @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @class([
            'rich-editor rich-text block min-h-40 w-full rounded-b-xl border-0 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-soft ring-1 ring-inset focus:ring-2 focus:ring-inset focus:outline-none dark:bg-white/5 dark:text-white',
            'ring-rose-300 focus:ring-rose-500' => $error,
            'ring-slate-200 focus:ring-brand-500 dark:ring-white/10' => ! $error,
        ])></trix-editor>
</x-ui.field>
