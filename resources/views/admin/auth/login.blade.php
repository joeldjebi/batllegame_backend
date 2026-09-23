@extends('layouts.app')
@section('title', 'Administration')
@section('content')
<section style="max-width:420px;margin:60px auto">
    <h1>Administration de la plateforme</h1>
    <p class="muted">Accès réservé au super-administrateur.</p>
    <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <div class="row"><label style="flex:1">Email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label></div>
        <div class="row"><label style="flex:1">Mot de passe <input type="password" name="password" required autocomplete="current-password"></label></div>
        <div class="row"><button>Se connecter</button></div>
    </form>
</section>
@endsection
