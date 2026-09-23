@extends('layouts.app')
@section('title', 'Connexion')
@section('content')
<section style="max-width:420px;margin:60px auto">
    <h1>Connexion</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="row"><x-phone-input :countries="$countries" /></div>
        <div class="row"><label style="flex:1">Mot de passe <input type="password" name="password" required></label></div>
        <div class="row"><label style="flex-direction:row;gap:6px"><input type="checkbox" name="remember" value="1"> Rester connecté</label></div>
        <div class="row"><button>Se connecter</button></div>
    </form>
</section>
@endsection
