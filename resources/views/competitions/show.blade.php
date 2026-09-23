@extends('layouts.app')
@section('title', $competition->name)
@section('content')
<p><a href="{{ route('organizers.show', $organizer) }}">← {{ $organizer->name }}</a></p>
<h1>{{ $competition->name }} <span class="badge">{{ $competition->status->label() }}</span></h1>

<section>
    <h2>Statut</h2>
    @foreach ($competition->status->nextStatuses() as $next)
        @can('changeStatus', [$competition, $next])
            <form class="inline" method="POST" action="{{ route('organizers.competitions.status', [$organizer, $competition]) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $next->value }}">
                <button class="{{ $next === \App\Enums\CompetitionStatus::Cancelled ? 'danger' : '' }}">→ {{ $next->label() }}</button>
            </form>
        @endcan
    @endforeach
    @can('delete', $competition)
        <form class="inline" method="POST" action="{{ route('organizers.competitions.destroy', [$organizer, $competition]) }}">@csrf @method('DELETE')<button class="danger">Supprimer</button></form>
    @endcan
    <p class="muted">{{ $competition->discipline->label() }} · {{ $competition->mode->label() }} · slug : {{ $competition->slug }}</p>
</section>

<section>
    <h2>Phases</h2>
    <table>
        <tr><th>#</th><th>Type</th><th>Mode</th><th>Vote</th><th>Statut</th><th></th></tr>
        @forelse ($competition->phases as $phase)
            <tr>
                <td>{{ $phase->position }}</td>
                <td>{{ $phase->type->label() }} @if ($phase->qualifiers_per_group)<span class="muted">({{ $phase->rules->groupCount }} poules, {{ $phase->qualifiers_per_group }} qualifiés)</span>@endif</td>
                <td>{{ $phase->effectiveMode()->label() }}</td>
                <td>{{ $phase->rules->voteMode->label() }} ({{ $phase->rules->juryWeight }}/{{ $phase->rules->publicWeight }})</td>
                <td><span class="badge">{{ $phase->status->label() }}</span></td>
                <td>
                    @if (! $phase->isFrozen())
                        @can('update', $competition)
                            <form class="inline" method="POST" action="{{ route('organizers.competitions.phases.start', [$organizer, $competition, $phase]) }}">@csrf<button>Démarrer</button></form>
                            <form class="inline" method="POST" action="{{ route('organizers.competitions.phases.destroy', [$organizer, $competition, $phase]) }}">@csrf @method('DELETE')<button class="danger">Supprimer</button></form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Aucune phase.</td></tr>
        @endforelse
    </table>
    @can('update', $competition)
        <form method="POST" action="{{ route('organizers.competitions.phases.store', [$organizer, $competition]) }}" class="row">
            @csrf
            <label>Type <select name="type">@foreach (\App\Enums\PhaseType::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Mode <select name="mode"><option value="">Hérité</option>@foreach (\App\Enums\CompetitionMode::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Nb poules <input type="number" name="rules[group_count]" min="1"></label>
            <label>Qualifiés/poule <input type="number" name="qualifiers_per_group" min="1"></label>
            <label>Passages <input type="number" name="rules[rounds]" value="1" min="1"></label>
            <label>Durée (s) <input type="number" name="rules[turn_duration]" value="60" min="15"></label>
            <label>Vote <select name="rules[vote_mode]">@foreach (\App\Enums\VoteMode::options() as $value => $label)<option value="{{ $value }}" @selected($value === 'mixte')>{{ $label }}</option>@endforeach</select></label>
            <label>% jury <input type="number" name="rules[jury_weight]" value="50" min="0" max="100"></label>
            <label>% public <input type="number" name="rules[public_weight]" value="50" min="0" max="100"></label>
            <button>Ajouter la phase</button>
        </form>
    @endcan
</section>

@foreach ($competition->phases->filter->isFrozen() as $phase)
<section>
    <h2>Phase {{ $phase->position }} · {{ $phase->type->label() }} <span class="badge">{{ $phase->status->label() }}</span></h2>

    @foreach ($phase->groups as $group)
        <h3>{{ $group->name }}</h3>
        <table>
            <tr><th>#</th><th>Participant</th><th>Pts</th><th>V</th><th>N</th><th>D</th><th>Diff</th></tr>
            @foreach ($group->standings as $standing)
                <tr><td>{{ $standing->rank ?? '—' }}</td><td>{{ $standing->participant->stage_name }}</td><td>{{ $standing->points }}</td><td>{{ $standing->wins }}</td><td>{{ $standing->draws }}</td><td>{{ $standing->losses }}</td><td>{{ $standing->score_diff }}</td></tr>
            @endforeach
        </table>
    @endforeach

    <h3>Matchs</h3>
    <table>
        <tr><th>Match</th><th>Participants</th><th>Scores</th><th>Statut</th><th></th></tr>
        @foreach ($phase->matches as $match)
            <tr>
                <td>{{ $match->group?->name ?? $match->bracket?->label() }} · T{{ $match->round }} #{{ $match->bracket_position }}</td>
                <td>
                    @foreach ($match->slots as $slot)
                        <div>@if ($match->winner_id && $slot->participant_id === $match->winner_id)🏆 @endif{{ $slot->participant?->stage_name ?? '—' }}</div>
                    @endforeach
                </td>
                <td>
                    @foreach ($match->slots as $slot)
                        <div class="muted">{{ $slot->final_score ?? '—' }} <small>(jury {{ $slot->jury_score ?? '—' }} · public {{ $slot->public_score ?? '—' }})</small></div>
                    @endforeach
                </td>
                <td><span class="badge">{{ $match->status->label() }}</span></td>
                <td>
                    @can('runMatches', $competition)
                        @if (in_array($match->status, [\App\Enums\MatchStatus::Scheduled, \App\Enums\MatchStatus::Submissions], true) && $match->slots->whereNotNull('participant_id')->count() === 2)
                            <form class="inline" method="POST" action="{{ route('organizers.competitions.matches.open-voting', [$organizer, $competition, $match]) }}">@csrf<button class="secondary">Ouvrir le vote</button></form>
                        @endif
                        @if ($match->status === \App\Enums\MatchStatus::Voting)
                            <form class="inline" method="POST" action="{{ route('organizers.competitions.matches.close', [$organizer, $competition, $match]) }}">
                                @csrf
                                <select name="winner_id" title="Uniquement en cas d'égalité parfaite">
                                    <option value="">Vainqueur auto</option>
                                    @foreach ($match->slots as $slot)<option value="{{ $slot->participant_id }}">{{ $slot->participant?->stage_name }}</option>@endforeach
                                </select>
                                <button>Clôturer</button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @endforeach
    </table>
</section>
@endforeach

<section>
    <h2>Critères du jury</h2>
    <table>
        <tr><th>Nom</th><th>Max</th><th>Poids</th><th></th></tr>
        @foreach ($competition->criteria as $criterion)
            <tr>
                <td>{{ $criterion->name }}</td><td>{{ $criterion->max_points }}</td><td>{{ $criterion->weight }}</td>
                <td>@can('update', $competition)<form class="inline" method="POST" action="{{ route('organizers.competitions.criteria.destroy', [$organizer, $competition, $criterion]) }}">@csrf @method('DELETE')<button class="danger">Supprimer</button></form>@endcan</td>
            </tr>
        @endforeach
    </table>
    @can('update', $competition)
        <form method="POST" action="{{ route('organizers.competitions.criteria.store', [$organizer, $competition]) }}" class="row">
            @csrf
            <label>Nom <input name="name" required></label>
            <label>Note max <input type="number" name="max_points" value="10" min="1" required></label>
            <label>Poids <input type="number" step="0.1" name="weight" value="1" min="0.1" required></label>
            <button>Ajouter</button>
        </form>
    @endcan
</section>

<section>
    <h2>Jury</h2>
    <table>
        <tr><th>Nom</th><th>Téléphone</th><th>Statut</th><th></th></tr>
        @foreach ($competition->judges as $judge)
            <tr>
                <td>{{ $judge->user->name }}</td><td>{{ $judge->user->phone }}</td><td>{{ $judge->status->label() }}</td>
                <td>@can('update', $competition)<form class="inline" method="POST" action="{{ route('organizers.competitions.judges.destroy', [$organizer, $competition, $judge]) }}">@csrf @method('DELETE')<button class="danger">Retirer</button></form>@endcan</td>
            </tr>
        @endforeach
    </table>
    @can('update', $competition)
        <form method="POST" action="{{ route('organizers.competitions.judges.store', [$organizer, $competition]) }}" class="row">
            @csrf
            <x-phone-input />
            <button>Inviter</button>
        </form>
    @endcan
</section>

<section>
    <h2>Participants ({{ $competition->participants->count() }}@if ($competition->max_participants) / {{ $competition->max_participants }}@endif)</h2>
    <table>
        <tr><th>Nom de scène</th><th>Compte</th><th>Seed</th><th>Statut</th><th></th></tr>
        @foreach ($competition->participants as $participant)
            <tr>
                <td>{{ $participant->stage_name }}</td>
                <td>{{ $participant->user->name }} <span class="muted">{{ $participant->user->phone }}</span></td>
                <td>{{ $participant->seed ?? '—' }}</td>
                <td><span class="badge">{{ $participant->status->label() }}</span></td>
                <td>
                    @can('manageRegistrations', $competition)
                        <form class="inline" method="POST" action="{{ route('organizers.competitions.participants.update', [$organizer, $competition, $participant]) }}">
                            @csrf @method('PATCH')
                            <input type="number" name="seed" value="{{ $participant->seed }}" min="1" style="width:70px" placeholder="seed">
                            <select name="status">
                                @foreach ([\App\Enums\ParticipantStatus::Validated, \App\Enums\ParticipantStatus::Withdrawn, \App\Enums\ParticipantStatus::Disqualified] as $status)
                                    <option value="{{ $status->value }}" @selected($participant->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <button class="secondary">OK</button>
                        </form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </table>
</section>
@endsection
