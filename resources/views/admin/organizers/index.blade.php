@extends('layouts.app')
@section('title', 'Organisateurs')
@section('content')
<h1>Organisateurs</h1>
<section>
    <table>
        <tr><th>Nom</th><th>Ville</th><th>Compétitions</th><th>Statut</th><th></th></tr>
        @foreach ($organizers as $organizer)
            <tr>
                <td><strong>{{ $organizer->name }}</strong> <span class="muted">{{ $organizer->slug }}</span></td>
                <td>{{ $organizer->city }}</td>
                <td>{{ $organizer->competitions_count }}</td>
                <td><span class="badge">{{ $organizer->status->label() }}</span></td>
                <td>
                    @foreach (\App\Enums\OrganizerStatus::cases() as $status)
                        @if ($status !== $organizer->status)
                            <form class="inline" method="POST" action="{{ route('admin.organizers.status', $organizer) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $status->value }}">
                                <button class="{{ $status === \App\Enums\OrganizerStatus::Suspended ? 'danger' : 'secondary' }}">{{ $status->label() }}</button>
                            </form>
                        @endif
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
    {{ $organizers->links() }}
</section>
@endsection
