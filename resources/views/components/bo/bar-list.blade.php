@props(['items', 'total' => null])

{{--
    Single-series horizontal bars (one hue): each row is labeled directly with
    its value, so no legend is needed. $items: [['label' => ..., 'value' => ..., 'href' => ?]].
--}}
@php($max = max(1, collect($items)->max('value')))

<ul {{ $attributes->class('space-y-3') }}>
    @foreach ($items as $item)
        @php($share = $total ? round($item['value'] / max(1, $total) * 100) : null)
        <li title="{{ $item['label'] }} : {{ $item['value'] }}{{ $share !== null ? ' ('.$share.' %)' : '' }}">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                @if (! empty($item['href']))
                    <a href="{{ $item['href'] }}" class="font-medium text-slate-700 hover:text-brand-600 dark:text-slate-200 dark:hover:text-brand-300">{{ $item['label'] }}</a>
                @else
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $item['label'] }}</span>
                @endif
                <span class="text-slate-900 tabular-nums dark:text-white">
                    <span class="font-semibold">{{ $item['value'] }}</span>
                    @if ($share !== null)<span class="ml-1 text-xs text-slate-400">{{ $share }} %</span>@endif
                </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-slate-100 dark:bg-white/5">
                <div class="h-2 rounded-full bg-brand-600 dark:bg-brand-400" style="width: {{ $item['value'] ? max(2, $item['value'] / $max * 100) : 0 }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
