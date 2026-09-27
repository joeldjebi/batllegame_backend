<x-layouts.portal title="Mes compétitions">
    <div class="mx-auto max-w-2xl">
        <x-app.title title="Mes compétitions" subtitle="Les compétitions pour lesquelles vous êtes membre du jury." />

        @if ($assignments->isEmpty())
            <x-ui.empty icon="scale" title="Aucune compétition" description="Aucun organisateur ne vous a encore confié de compétition." />
        @else
            <x-app.section :header="$assignments->count().' compétition'.($assignments->count() > 1 ? 's' : '')">
                @foreach ($assignments as $assignment)
                    @php($competition = $assignment->competition)
                    <x-app.row :href="route('jury.competitions.show', $competition)" :title="$competition->name"
                        :subtitle="$competition->organizer->name.' · '.$competition->mode->label().' · '.$competition->criteria_count.' critère(s)'">
                        <x-slot:leading>
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon :name="$competition->discipline->icon()" variant="s" class="size-5" /></span>
                        </x-slot:leading>
                        <x-slot:detail>
                            <span class="flex flex-wrap gap-1.5">
                                <x-app.tag :value="$competition->status" dot />
                                @if ($competition->voting_matches_count)
                                    <x-app.tag tone="red" dot>{{ $competition->voting_matches_count }} match(s) à noter</x-app.tag>
                                @else
                                    <x-app.tag>Rien à noter</x-app.tag>
                                @endif
                            </span>
                        </x-slot:detail>
                    </x-app.row>
                @endforeach
            </x-app.section>
        @endif
    </div>

    <x-realtime :channels="[\App\Realtime\Channel::user(auth('jury')->id()), ...$assignments->map(fn ($a) => \App\Realtime\Channel::jury($a->competition_id))->all()]" />
</x-layouts.portal>
