@props(['header' => null, 'footer' => null])

{{-- Rounded white section of rows, a caption above and a note below (iOS grouped list). --}}
<section {{ $attributes->class('mb-6') }}>
    @if ($header)
        <h2 class="mb-2 px-4 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">{{ $header }}</h2>
    @endif
    <div class="divide-y divide-slate-200/70 overflow-hidden rounded-2xl bg-white dark:divide-white/5 dark:bg-slate-900/70 [&>*]:border-slate-200/70">
        {{ $slot }}
    </div>
    @if ($footer)
        <p class="mt-2 px-4 text-xs text-slate-500 dark:text-slate-400">{{ $footer }}</p>
    @endif
</section>
