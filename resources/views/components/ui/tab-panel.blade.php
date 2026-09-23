@props(['name'])
<div x-show="tab === @js($name)" x-cloak {{ $attributes->class('animate-fade-in') }}>{{ $slot }}</div>
