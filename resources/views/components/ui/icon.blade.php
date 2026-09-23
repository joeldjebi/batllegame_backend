@props(['name', 'variant' => 'o'])
{{-- Heroicons: variant "o" (outline 24), "s" (solid 24), "m" (mini 20). --}}
<x-dynamic-component :component="'heroicon-'.$variant.'-'.$name" {{ $attributes->merge(['class' => 'shrink-0', 'aria-hidden' => 'true']) }} />
