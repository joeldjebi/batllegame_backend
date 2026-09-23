{{-- Styled table: header cells in the "head" slot, rows in the default slot. --}}
<div {{ $attributes->class('-mx-5 -mb-5 overflow-x-auto') }}>
    <table class="min-w-full divide-y divide-slate-100 dark:divide-white/5
        [&_th]:px-5 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:tracking-wider [&_th]:text-slate-500 [&_th]:uppercase dark:[&_th]:text-slate-400
        [&_td]:px-5 [&_td]:py-3.5 [&_td]:text-sm [&_td]:text-slate-700 dark:[&_td]:text-slate-300
        [&_tbody_tr]:transition hover:[&_tbody_tr]:bg-slate-50/70 dark:hover:[&_tbody_tr]:bg-white/[0.02]">
        @isset($head)
            <thead class="bg-slate-50/70 dark:bg-white/[0.02]"><tr>{{ $head }}</tr></thead>
        @endisset
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
            {{ $slot }}
        </tbody>
    </table>
</div>
