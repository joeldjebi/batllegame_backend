@props(['html'])

{{-- Displays organizer rich text. $html is sanitized on write (App\Support\RichText). --}}
@if (filled($html))
    <div {{ $attributes->class('rich-text text-sm leading-relaxed text-slate-600 dark:text-slate-300') }}>{!! $html !!}</div>
@endif
