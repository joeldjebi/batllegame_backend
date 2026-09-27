@php use App\Enums\MatchStatus; @endphp

<x-layouts.portal :title="$competition->name">
    <div class="mx-auto max-w-2xl">
        <x-app.title :title="$competition->name" :back="route('jury.dashboard')" back-label="Mes compétitions"
            :subtitle="$competition->organizer->name.' · '.$criteriaCount.' critère(s) de notation'">
            <x-slot:meta><x-app.tag :value="$competition->status" dot /></x-slot:meta>
        </x-app.title>

        @if ($competition->preselection)
            @php($pstate = $competition->preselection->state())
            <x-app.section>
                <x-app.row :href="route('jury.competitions.preselection', $competition)" icon="funnel" tone="brand" title="Présélection" subtitle="Notez les prestations des artistes candidats">
                    <x-slot:detail><x-app.tag :value="$pstate" dot /></x-slot:detail>
                </x-app.row>
            </x-app.section>
        @endif

        @if ($matches->isEmpty())
            <x-ui.empty icon="clock" title="Aucun match à noter pour l'instant" description="Les matchs apparaissent ici dès que l'organisateur ouvre le vote." />
        @else
            <x-app.section header="Matchs à noter">
                @foreach ($matches as $match)
                    @php($done = collect($scored->get($match->id, []))->count())
                    {{-- Artists to score in this match: 2 in a battle, all the members of a group. --}}
                    @php($toScore = $match->slots->filter(fn ($slot) => $slot->participant_id && ! $slot->is_forfeit)->count())
                    <x-app.row :href="route('jury.competitions.matches.show', [$competition, $match])" :title="$match->title()"
                        :subtitle="($match->stage?->name ?? 'Match').' · '.$match->phase->effectiveMode()->label()"
                        :icon="$match->status === MatchStatus::Voting ? 'scale' : 'lock-closed'" :tone="$match->status === MatchStatus::Voting ? 'red' : 'gray'">
                        <x-slot:trailing>
                            <span class="flex flex-col items-end gap-1">
                                <x-app.tag :value="$match->status" dot />
                                @if ($match->status === MatchStatus::Voting)
                                    <x-app.tag :tone="$done >= $toScore ? 'green' : 'amber'">{{ $done }}/{{ $toScore }} noté(s)</x-app.tag>
                                @endif
                            </span>
                        </x-slot:trailing>
                    </x-app.row>
                @endforeach
            </x-app.section>
        @endif
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::jury($competition->id), \App\Realtime\Channel::user(auth('jury')->id())]" />
</x-layouts.portal>
