@props(['tabs', 'default' => null, 'key' => 'tabs'])

{{--
    $tabs: ['key' => ['label' => ..., 'icon' => ..., 'count' => ...]].
    The active tab follows the URL hash and is remembered per page, so it
    survives the redirect back after a form submission.
--}}
<div x-data="{
        tab: null,
        storageKey: @js('tab:'.$key.':'.request()->path()),
        init() {
            const tabs = @js(array_keys($tabs));
            const wanted = location.hash.slice(1) || localStorage.getItem(this.storageKey);
            this.tab = tabs.includes(wanted) ? wanted : @js($default ?? array_key_first($tabs));
            this.$watch('tab', (value) => { localStorage.setItem(this.storageKey, value); history.replaceState(null, '', '#' + value); });
        },
    }" {{ $attributes }}>
    <div class="mb-6 overflow-x-auto border-b border-slate-200 dark:border-white/10">
        <nav class="-mb-px flex gap-6" aria-label="Onglets">
            @foreach ($tabs as $name => $tab)
                <button type="button" x-on:click="tab = @js($name)"
                    :class="tab === @js($name)
                        ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    class="group inline-flex items-center gap-2 border-b-2 px-0.5 py-3 text-sm font-medium whitespace-nowrap transition">
                    @isset($tab['icon'])<x-ui.icon :name="$tab['icon']" class="size-[1.125rem]" />@endisset
                    {{ $tab['label'] }}
                    @isset($tab['count'])
                        <span :class="tab === @js($name) ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-200' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'"
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums">{{ $tab['count'] }}</span>
                    @endisset
                </button>
            @endforeach
        </nav>
    </div>
    {{ $slot }}
</div>
