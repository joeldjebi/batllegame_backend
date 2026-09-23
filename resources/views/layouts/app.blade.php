<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Back-office') · {{ config('app.name') }}</title>
    <style>
        :root { --fg: #1f2328; --muted: #656d76; --border: #d0d7de; --accent: #8250df; --danger: #cf222e; --bg: #fff; --soft: #f6f8fa; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, sans-serif; color: var(--fg); background: var(--soft); }
        header { background: var(--bg); border-bottom: 1px solid var(--border); padding: 12px 24px; display: flex; gap: 16px; align-items: center; }
        header a { color: var(--fg); text-decoration: none; font-weight: 600; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        section { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; }
        h1 { font-size: 24px; margin: 0 0 16px; } h2 { font-size: 18px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
        form.inline { display: inline; } .row { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-top: 8px; }
        label { display: flex; flex-direction: column; font-size: 13px; color: var(--muted); gap: 2px; }
        input, select, textarea { font: inherit; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; }
        button { font: inherit; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--accent); background: var(--accent); color: #fff; cursor: pointer; }
        button.secondary { background: var(--bg); color: var(--fg); border-color: var(--border); } button.danger { background: var(--danger); border-color: var(--danger); }
        .flash { background: #dafbe1; border: 1px solid #4ac26b; padding: 8px 12px; border-radius: 6px; margin-bottom: 16px; }
        .errors { background: #ffebe9; border: 1px solid var(--danger); padding: 8px 12px; border-radius: 6px; margin-bottom: 16px; }
        .badge { display: inline-block; padding: 0 8px; border-radius: 999px; background: var(--soft); border: 1px solid var(--border); font-size: 12px; }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
@auth
    <header>
        @if (request()->routeIs('admin.*'))
            <a href="{{ route('admin.organizers.index') }}">{{ config('app.name') }} · Administration</a>
        @else
            <a href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
        @endif
        <span class="muted" style="margin-left:auto">{{ auth()->user()->name }}</span>
        <form class="inline" method="POST" action="{{ request()->routeIs('admin.*') ? route('admin.logout') : route('logout') }}">@csrf<button class="secondary">Déconnexion</button></form>
    </header>
@endauth
<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @yield('content')
</main>
</body>
</html>
