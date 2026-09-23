@php
    use App\Enums\OrganizerStatus;

    $status = request('status');
    $filters = ['' => 'Tous', 'en_attente' => 'À vérifier', 'verifie' => 'Vérifiés', 'suspendu' => 'Suspendus'];
@endphp

<x-layouts.app title="Organisateurs">
    <x-ui.page-header title="Organisateurs" description="Vérifiez les nouvelles structures et gardez le contrôle de la plateforme." :breadcrumbs="['Console' => route('admin.organizers.index'), 'Organisateurs' => null]" />

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Organisateurs" :value="$counts->sum()" icon="building-office-2" />
        <x-ui.stat label="À vérifier" :value="$counts['en_attente'] ?? 0" icon="clock" tone="amber" hint="En attente de validation" />
        <x-ui.stat label="Vérifiés" :value="$counts['verifie'] ?? 0" icon="check-badge" tone="green" />
        <x-ui.stat label="Compétitions" :value="$competitionsCount" icon="trophy" tone="blue" hint="Sur toute la plateforme" />
    </div>

    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-white/5">
            <nav class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5">
                @foreach ($filters as $value => $label)
                    <a href="{{ route('admin.organizers.index', array_filter(['status' => $value, 'q' => request('q')])) }}" @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-soft dark:bg-white/10 dark:text-white' => (string) $status === (string) $value,
                        'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => (string) $status !== (string) $value,
                    ])>
                        {{ $label }}
                        @if ($value && ($counts[$value] ?? 0))<span class="ml-1 text-xs text-slate-400 tabular-nums">{{ $counts[$value] }}</span>@endif
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="relative">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <x-ui.icon name="magnifying-glass" variant="m" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un organisateur…" class="w-64 rounded-lg border-0 bg-slate-50 py-1.5 pr-3 pl-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
            </form>
        </div>

        <div class="p-5">
            @if ($organizers->isEmpty())
                <x-ui.empty icon="building-office-2" title="Aucun organisateur" description="Aucun résultat pour ces filtres." />
            @else
                <x-ui.table>
                    <x-slot:head><th>Organisateur</th><th>Propriétaire</th><th>Activité</th><th>Statut</th><th class="!text-right">Actions</th></x-slot:head>
                    @foreach ($organizers as $organizer)
                        @php($owner = $organizer->members->first()?->user)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$organizer->name" :src="$organizer->logo_path ? Storage::url($organizer->logo_path) : null" square />
                                    <div>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $organizer->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $organizer->city ?? '—' }} · créé le {{ $organizer->created_at->translatedFormat('d M Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($owner)<p>{{ $owner->name }}</p><p class="text-xs text-slate-500">{{ $owner->email }}</p>@else<span class="text-slate-400">—</span>@endif
                            </td>
                            <td class="text-slate-500">
                                <span class="inline-flex items-center gap-1"><x-ui.icon name="trophy" variant="m" class="size-4" />{{ $organizer->competitions_count }}</span>
                                <span class="ml-3 inline-flex items-center gap-1"><x-ui.icon name="user-group" variant="m" class="size-4" />{{ $organizer->members_count }}</span>
                            </td>
                            <td>
                                <x-ui.badge :value="$organizer->status" />
                                @if ($organizer->verified_at)<p class="mt-1 text-[11px] text-slate-400">depuis le {{ $organizer->verified_at->translatedFormat('d M Y') }}</p>@endif
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    @if ($organizer->status !== OrganizerStatus::Verified)
                                        <x-ui.confirm :action="route('admin.organizers.status', $organizer)" method="PATCH" :danger="false" icon="check-badge"
                                            :title="'Vérifier '.$organizer->name.' ?'" message="L'organisateur pourra ouvrir des inscriptions publiques." confirm="Vérifier">
                                            <x-slot:fields><input type="hidden" name="status" value="verifie"></x-slot:fields>
                                            <x-ui.button size="sm" variant="soft" icon="check-badge">{{ $organizer->isSuspended() ? 'Réactiver' : 'Vérifier' }}</x-ui.button>
                                        </x-ui.confirm>
                                    @endif
                                    @unless ($organizer->isSuspended())
                                        <x-ui.confirm :action="route('admin.organizers.status', $organizer)" method="PATCH" icon="no-symbol"
                                            :title="'Suspendre '.$organizer->name.' ?'" message="Toutes ses compétitions seront figées : aucune modification, aucun vote." confirm="Suspendre">
                                            <x-slot:fields><input type="hidden" name="status" value="suspendu"></x-slot:fields>
                                            <x-ui.button size="sm" variant="danger-soft" icon="no-symbol">Suspendre</x-ui.button>
                                        </x-ui.confirm>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>

        @if ($organizers->hasPages())
            <x-slot:footer>{{ $organizers->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</x-layouts.app>
