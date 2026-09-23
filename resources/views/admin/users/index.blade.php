@php($types = ['' => 'Tous'] + \App\Http\Controllers\Admin\UserController::TYPES)

<x-layouts.app title="Utilisateurs">
    <x-ui.page-header title="Utilisateurs" :description="number_format($total, 0, ',', ' ').' compte(s) sur la plateforme : membres du back-office, artistes, jurés et public.'" :breadcrumbs="['Console' => route('admin.dashboard'), 'Utilisateurs' => null]" />

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-white/5">
            <nav class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">
                @foreach ($types as $value => $label)
                    <a href="{{ route('admin.users.index', array_filter(['type' => $value, 'q' => request('q')])) }}" @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-soft dark:bg-white/10 dark:text-white' => (string) $type === (string) $value,
                        'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => (string) $type !== (string) $value,
                    ])>{{ $label }}</a>
                @endforeach
            </nav>
            <form method="GET" class="relative">
                @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, email ou téléphone…" class="w-64 rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
            </form>
        </div>

        <div class="p-5">
            @if ($users->isEmpty())
                <x-ui.empty icon="users" title="Aucun utilisateur" description="Aucun résultat pour ces filtres." />
            @else
                <x-ui.table>
                    <x-slot:head><th>Utilisateur</th><th>Contact</th><th>Profil</th><th>Activité</th><th>Inscrit le</th><th></th></x-slot:head>
                    @foreach ($users as $account)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$account->name" size="sm" />
                                    <a href="{{ route('admin.users.show', $account) }}" class="font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-300">{{ $account->name }}</a>
                                </div>
                            </td>
                            <td>
                                <p>{{ $account->country?->flag }} {{ $account->phone }}</p>
                                <p class="text-xs text-slate-500">{{ $account->email ?? 'Pas d\'email' }}</p>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ($account->isPlatformAdmin())<x-ui.badge tone="red" :dot="false" icon="shield-check">Super-admin</x-ui.badge>@endif
                                    @if ($account->organizer_memberships_count)<x-ui.badge tone="violet" :dot="false">Back-office</x-ui.badge>@endif
                                    @if ($account->participations_count)<x-ui.badge tone="blue" :dot="false">Artiste</x-ui.badge>@endif
                                    @if ($account->judge_assignments_count)<x-ui.badge tone="green" :dot="false">Juré</x-ui.badge>@endif
                                    @if (! $account->phone_verified_at)<x-ui.badge tone="amber">Non vérifié</x-ui.badge>@endif
                                </div>
                            </td>
                            <td class="text-xs text-slate-500 tabular-nums">
                                {{ $account->participations_count }} inscr. · {{ $account->public_votes_count }} vote(s)
                            </td>
                            <td class="text-slate-500">{{ $account->created_at->translatedFormat('d M Y') }}</td>
                            <td class="text-right"><x-ui.button size="sm" variant="secondary" :href="route('admin.users.show', $account)" icon="eye">Détails</x-ui.button></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>

        @if ($users->hasPages())
            <x-slot:footer>{{ $users->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</x-layouts.app>
