@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'icon' => null, 'suffix' => null])

@php
    // "settings[timezone]" => "settings.timezone" for errors and old input.
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', $key).'-'.substr(md5(uniqid('', true)), 0, 5));
    $error = $errors->first($key);
    $current = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : $value);
@endphp

<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint" :required="$attributes->has('required')" :class="$attributes->get('class')">
    <div class="relative">
        @if ($icon)
            <x-ui.icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
            {{ $attributes->except('class')->class([
                'block w-full rounded-xl border-0 bg-white py-2.5 text-sm text-slate-900 shadow-soft ring-1 ring-inset placeholder:text-slate-400 transition focus:ring-2 focus:ring-inset dark:bg-white/5 dark:text-white',
                'pl-10' => $icon, 'pr-12' => $suffix,
                'ring-rose-300 focus:ring-rose-500 dark:ring-rose-500/50' => $error,
                'ring-slate-200 focus:ring-brand-500 dark:ring-white/10 dark:focus:ring-brand-400' => ! $error,
            ]) }}>
        @if ($suffix)
            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium text-slate-400">{{ $suffix }}</span>
        @endif
    </div>
</x-ui.field>
