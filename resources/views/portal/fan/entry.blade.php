@php
    use App\Enums\PreselectionState;

    $artist = $entry->participant->stage_name;
    $state = $preselection->state();
    $url = route('fan.competitions.preselection.entry', [$competition, $entry]);
    $pitch = "Soutiens {$artist} dans « {$competition->name} » : un like peut tout changer.";
@endphp

<x-layouts.portal :title="$artist.' · '.$competition->name">
    <x-og :title="$artist.' · '.$competition->name" :description="$likes['can_like'] ? $pitch : 'Découvre la prestation de '.$artist.' sur Battle Game.'" :url="$url" :video="$entry->media_type === \App\Enums\MediaType::Video ? $entry->mediaUrl() : null" />

    <div class="mx-auto max-w-2xl">
        <x-ui.page-header :title="$artist" :breadcrumbs="['Compétitions' => route('fan.dashboard'), $competition->name => route('fan.competitions.show', $competition), $artist => null]">
            <x-slot:description>
                <x-ui.badge :value="$state" />
                <span>{{ $competition->name }} · {{ $competition->organizer->name }}</span>
            </x-slot:description>
        </x-ui.page-header>

        <section x-data="preselectionLikes({
            likeUrl: '{{ route('fan.competitions.preselection.like', [$competition, '__ENTRY__']) }}',
            unlikeUrl: '{{ route('fan.competitions.preselection.unlike', $competition) }}',
            loginUrl: '{{ route('fan.login') }}',
            loggedIn: {{ $user ? 'true' : 'false' }},
        })" class="space-y-4">
            <script type="application/json" x-ref="state">@json($likes)</script>

            @if ($likes['can_like'])
                <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-fuchsia-50 px-4 py-3 text-sm text-fuchsia-800 ring-1 ring-fuchsia-600/20 dark:bg-fuchsia-500/10 dark:text-fuchsia-200" x-data="countdown('{{ $preselection->voteEndsAt()->toIso8601String() }}')">
                    <x-ui.icon name="heart" variant="s" class="size-4 shrink-0" />
                    <span>Un seul like par compétition. Encore <strong class="font-display tabular-nums" x-text="label">{{ $preselection->voteEndsAt()->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</strong> pour soutenir {{ $artist }}.</span>
                </div>
            @endif

            <x-portal.entry-card :entry="$entry" :competition="$competition" :likes="$likes" :user="$user" :published="$state === PreselectionState::Published" large />

            <div class="flex flex-col gap-3 rounded-3xl bg-slate-950 p-5 text-white sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-display text-lg font-bold">Invite tes proches à soutenir {{ $artist }}</p>
                    <p class="text-sm text-white/60">Chaque like compte dans la sélection finale.</p>
                </div>
                <x-portal.share :url="$url" :title="$artist.' · '.$competition->name" :text="$pitch" label="Partager la prestation" variant="primary" size="lg" align="left" />
            </div>

            @if ($others > 0)
                <x-ui.button variant="secondary" size="lg" class="w-full" :href="route('fan.competitions.show', $competition).'#entry-'.$entry->id" icon="squares-2x2">
                    Voir les {{ $others }} autre(s) prestation(s)
                </x-ui.button>
            @endif
        </section>
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::competition($competition->id), $user ? \App\Realtime\Channel::user($user->id) : null]" />
</x-layouts.portal>
