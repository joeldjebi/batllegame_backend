@props(['entry', 'competition', 'likes', 'user' => null, 'published' => false, 'large' => false])

{{-- A pre-selection entry for the public: player, likes (AJAX, inside x-data="preselectionLikes") and sharing. --}}
@php
    $liked = $likes['my_like'] === $entry->id;
    $count = $likes['counts'][$entry->id] ?? null;
    $shareUrl = route('fan.competitions.preselection.entry', [$competition, $entry]);
@endphp

<x-ui.card :padding="false" id="entry-{{ $entry->id }}" class="scroll-mt-24 transition" x-bind:class="isLiked({{ $entry->id }}) && 'ring-2 ring-fuchsia-400'">
    <div @class(['p-4', 'sm:p-5' => $large])>
        <div class="mb-3 flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2.5">
                <x-ui.avatar :name="$entry->participant->stage_name" :src="$entry->participant->user?->avatarUrl()" :size="$large ? 'md' : 'sm'" />
                @if ($large)
                    <p class="truncate font-display text-lg font-bold">{{ $entry->participant->stage_name }}</p>
                @else
                    <a href="{{ $shareUrl }}" class="truncate font-display font-semibold hover:text-brand-700 dark:hover:text-brand-300">{{ $entry->participant->stage_name }}</a>
                @endif
            </div>
            @if ($published)
                @if ($entry->selected)<x-ui.badge tone="green" icon="trophy" :dot="false">Retenu · {{ $entry->rank }}e</x-ui.badge>@else<x-ui.badge tone="gray" :dot="false">{{ $entry->rank }}e</x-ui.badge>@endif
            @endif
        </div>
        <x-bo.media-player :performance="$entry" />
    </div>
    <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 dark:border-white/5">
        <span class="inline-flex min-w-0 items-center gap-1.5 text-sm text-slate-500">
            <span class="relative size-4 shrink-0">
                <span class="absolute inset-0" x-show="isLiked({{ $entry->id }})" x-transition.scale.90><x-ui.icon name="heart" variant="s" class="size-4 text-fuchsia-500" /></span>
                <span class="absolute inset-0" x-show="! isLiked({{ $entry->id }})"><x-ui.icon name="heart" variant="s" class="size-4 text-slate-300 dark:text-slate-600" /></span>
            </span>
            <span class="truncate font-semibold tabular-nums" x-text="label({{ $entry->id }})">{{ $count !== null ? $count.' like'.($count > 1 ? 's' : '') : ($liked ? 'Votre like' : '') }}</span>
        </span>
        <div class="flex shrink-0 items-center gap-1.5">
            <x-portal.share :url="$shareUrl" icon :title="$entry->participant->stage_name.' · '.$competition->name"
                :text="'Soutiens '.$entry->participant->stage_name.' dans « '.$competition->name.' » : un like peut tout changer ❤️'" />
            @if ($likes['can_like'])
                @if (! $user)
                    <x-ui.button size="sm" :href="route('fan.login')" icon="heart">J'aime</x-ui.button>
                @else
                    <button type="button" x-on:click="toggle({{ $entry->id }})" x-bind:disabled="busy" x-bind:aria-pressed="isLiked({{ $entry->id }})"
                        x-bind:class="isLiked({{ $entry->id }})
                            ? 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20 hover:bg-fuchsia-100 dark:bg-fuchsia-500/10 dark:text-fuchsia-200'
                            : (myLike === null ? 'bg-brand-600 text-white ring-brand-600 hover:bg-brand-500' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50 dark:bg-white/5 dark:text-slate-200 dark:ring-white/10')"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold ring-1 transition active:scale-95 disabled:opacity-60">
                        <x-ui.icon name="heart" variant="s" class="size-4" />
                        <span x-text="isLiked({{ $entry->id }}) ? 'Je n\'aime plus' : (myLike === null ? 'J\'aime' : 'Déplacer mon like')">{{ $liked ? "Je n'aime plus" : ($likes['my_like'] ? 'Déplacer mon like' : "J'aime") }}</span>
                    </button>
                @endif
            @endif
        </div>
    </div>
</x-ui.card>
