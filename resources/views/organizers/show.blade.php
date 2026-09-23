@extends('layouts.app')
@section('title', $organizer->name)
@section('content')
<h1>{{ $organizer->name }} <span class="badge">{{ $organizer->status->label() }}</span></h1>

<section>
    <h2>Compétitions</h2>
    <table>
        <tr><th>Nom</th><th>Discipline</th><th>Mode</th><th>Statut</th></tr>
        @forelse ($competitions as $competition)
            <tr>
                <td><a href="{{ route('organizers.competitions.show', [$organizer, $competition]) }}">{{ $competition->name }}</a></td>
                <td>{{ $competition->discipline->label() }}</td>
                <td>{{ $competition->mode->label() }}</td>
                <td><span class="badge">{{ $competition->status->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Aucune compétition.</td></tr>
        @endforelse
    </table>

    @can('create', [\App\Models\Competition::class, $organizer])
        <form method="POST" action="{{ route('organizers.competitions.store', $organizer) }}" class="row">
            @csrf
            <label>Nom <input name="name" required></label>
            <label>Discipline <select name="discipline">@foreach (\App\Enums\Discipline::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Mode <select name="mode">@foreach (\App\Enums\CompetitionMode::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label>Fin des inscriptions <input type="datetime-local" name="registration_ends_at"></label>
            <label>Max participants <input type="number" name="max_participants" min="2"></label>
            <label>Frais (XOF) <input type="number" name="entry_fee" min="0" value="0"></label>
            <button>Créer la compétition</button>
        </form>
    @endcan
</section>

<section>
    <h2>Membres</h2>
    <table>
        <tr><th>Nom</th><th>Téléphone</th><th>Rôle</th><th></th></tr>
        @foreach ($members as $member)
            <tr>
                <td>{{ $member->user->name }}</td>
                <td>{{ $member->user->phone }}</td>
                <td>
                    @can('manageMembers', $organizer)
                        <form class="inline" method="POST" action="{{ route('organizers.members.update', [$organizer, $member]) }}">
                            @csrf @method('PATCH')
                            <select name="role" onchange="this.form.submit()">@foreach (\App\Enums\OrganizerRole::options() as $value => $label)<option value="{{ $value }}" @selected($member->role->value === $value)>{{ $label }}</option>@endforeach</select>
                        </form>
                    @else
                        {{ $member->role->label() }}
                    @endcan
                </td>
                <td>
                    @can('manageMembers', $organizer)
                        <form class="inline" method="POST" action="{{ route('organizers.members.destroy', [$organizer, $member]) }}">@csrf @method('DELETE')<button class="danger">Retirer</button></form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </table>
    @can('manageMembers', $organizer)
        <form method="POST" action="{{ route('organizers.members.store', $organizer) }}" class="row">
            @csrf
            <x-phone-input />
            <label>Rôle <select name="role"><option value="staff">Staff</option><option value="admin">Administrateur</option></select></label>
            <button>Ajouter</button>
        </form>
    @endcan
</section>
@endsection
