@extends('layouts.app')
@section('title', 'Mes organisateurs')
@section('content')
<h1>Mes organisateurs</h1>
<section>
    @forelse ($organizers as $organizer)
        <div class="row" style="justify-content:space-between">
            <a href="{{ route('organizers.show', $organizer) }}"><strong>{{ $organizer->name }}</strong></a>
            <span><span class="badge">{{ $organizer->status->label() }}</span> <span class="badge">{{ $organizer->membership->role->label() }}</span></span>
        </div>
    @empty
        <p class="muted">Vous n'êtes membre d'aucun organisateur.</p>
    @endforelse
</section>
<section>
    <h2>Créer un organisateur</h2>
    <form method="POST" action="{{ route('organizers.store') }}" enctype="multipart/form-data" class="row">
        @csrf
        <label>Nom <input name="name" value="{{ old('name') }}" required></label>
        <label>Ville <input name="city" value="{{ old('city') }}"></label>
        <label>Logo <input type="file" name="logo" accept="image/*"></label>
        <button>Créer</button>
    </form>
</section>
@endsection
