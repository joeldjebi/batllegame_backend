@php
    use App\Enums\CompetitionMode;
    use App\Enums\CompetitionStatus;
    use App\Enums\Discipline;
    use App\Enums\OrganizerRole;

    $canCreate = auth()->user()->can('create', [\App\Models\Competition::class, $organizer]);
    $canManageMembers = auth()->user()->can('manageMembers', $organizer);
    $canEdit = auth()->user()->can('update', $organizer);
    $byStatus = $competitions->countBy(fn ($c) => $c->status->value);
@endphp

<x-layouts.app :title="$organizer->name">
    <x-ui.page-header :title="$organizer->name" :breadcrumbs="['Tableau de bord' => route('dashboard'), $organizer->name => null]">
        <x-slot:leading>
            <x-ui.avatar :name="$organizer->name" :src="$organizer->logoUrl()" size="lg" square />
        </x-slot:leading>
        <x-slot:description>
            <x-ui.badge :value="$organizer->status" />
            @if ($organizer->locationLabel())<span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" variant="m" class="size-4" />{{ $organizer->locationLabel() }}</span>@endif
            <span class="inline-flex items-center gap-1"><x-ui.icon name="user-group" variant="m" class="size-4" />{{ $members->count() }} membre(s)</span>
        </x-slot:description>
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button variant="primary" icon="plus" :href="route('organizers.competitions.create', $organizer)">Nouvelle compétition</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div data-live="organizer-status">
    @if ($organizer->status === \App\Enums\OrganizerStatus::Pending)
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200">
            <x-ui.icon name="clock" class="size-5 shrink-0" />
            <p><strong>Vérification en cours.</strong> Vous pouvez préparer vos compétitions en brouillon ; l'ouverture des inscriptions sera possible dès que la plateforme aura vérifié votre organisation.</p>
        </div>
    @elseif ($organizer->isSuspended())
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200">
            <x-ui.icon name="no-symbol" class="size-5 shrink-0" />
            <p><strong>Organisateur suspendu.</strong> Les compétitions restent consultables mais aucune modification n'est possible.</p>
        </div>
    @endif
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Compétitions" :value="$competitions->count()" icon="trophy" />
        <x-ui.stat label="Inscriptions ouvertes" :value="$byStatus[CompetitionStatus::Registration->value] ?? 0" icon="user-plus" tone="blue" />
        <x-ui.stat label="En cours" :value="$byStatus[CompetitionStatus::InProgress->value] ?? 0" icon="fire" tone="red" />
        <x-ui.stat label="Participants" :value="$competitions->sum('participants_count')" icon="users" tone="green" />
    </div>

    <x-ui.tabs key="organizer" :tabs="[
        'competitions' => ['label' => 'Compétitions', 'icon' => 'trophy', 'count' => $competitions->count()],
        'members' => ['label' => 'Membres', 'icon' => 'user-group', 'count' => $members->count()],
        'profile' => ['label' => 'Profil', 'icon' => 'identification'],
    ]">
        <x-ui.tab-panel name="competitions">
            <div data-live="organizer-competitions">
            @if ($competitions->isEmpty())
                <x-ui.empty icon="trophy" title="Aucune compétition" description="Créez votre première compétition, ajoutez ses phases puis ouvrez les inscriptions.">
                    @if ($canCreate)<x-ui.button icon="plus" :href="route('organizers.competitions.create', $organizer)">Créer une compétition</x-ui.button>@endif
                </x-ui.empty>
            @else
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-slate-500">{{ $competitions->count() > 6 ? 'Les 6 plus récentes sur '.$competitions->count().'.' : $competitions->count().' compétition(s).' }}</p>
                    <x-ui.button size="sm" variant="secondary" icon="list-bullet" :href="route('organizers.competitions.index', $organizer)">Gérer toutes les compétitions</x-ui.button>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($competitions->take(6) as $competition)
                        @php($max = $competition->max_participants)
                        <a href="{{ route('organizers.competitions.show', [$organizer, $competition]) }}"
                            class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-900/5 transition hover:-translate-y-1 hover:shadow-lift hover:ring-brand-300 dark:bg-slate-900/60 dark:ring-white/10 dark:hover:ring-brand-500/40">
                            <div class="relative h-24 overflow-hidden bg-brand-600">
                                <x-ui.icon :name="$competition->discipline->icon()" class="absolute -right-3 -bottom-4 size-24 text-white/15 transition group-hover:scale-110" />
                                <div class="absolute top-3 left-3"><x-ui.badge :value="$competition->status" class="!bg-white/90 dark:!bg-slate-900/80" /></div>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="font-display text-base font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $competition->name }}</h3>
                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->discipline->icon()" variant="m" class="size-3.5" />{{ $competition->discipline->label() }}</span>
                                    <span class="inline-flex items-center gap-1"><x-ui.icon :name="$competition->mode->icon()" variant="m" class="size-3.5" />{{ $competition->mode->label() }}</span>
                                    @if ($competition->entry_fee)<span class="inline-flex items-center gap-1"><x-ui.icon name="banknotes" variant="m" class="size-3.5" />{{ number_format($competition->entry_fee, 0, ',', ' ') }} {{ $competition->currency }}</span>@endif
                                </div>
                                <div class="mt-auto pt-5">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-slate-500 dark:text-slate-400">{{ $competition->participants_count }}{{ $max ? ' / '.$max : '' }} inscrits</span>
                                        @if ($competition->registration_ends_at)<span class="text-slate-400">jusqu'au {{ $competition->registration_ends_at->translatedFormat('d M') }}</span>@endif
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-brand-600" style="width: {{ $max ? min(100, round($competition->participants_count / $max * 100)) : min(100, $competition->participants_count * 5) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
            </div>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="members">
            <x-ui.card title="Équipe" description="Owner : tout · Admin : configure les compétitions · Staff : gère les inscriptions et les matchs" icon="user-group">
                @if ($canManageMembers)
                    <x-slot:actions>
                        <x-ui.button size="sm" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'add-member')">Ajouter un manager</x-ui.button>
                    </x-slot:actions>
                @endif
                <x-ui.table>
                    <x-slot:head><th>Membre</th><th>Rôle</th><th>Depuis</th><th></th></x-slot:head>
                    @foreach ($members as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$member->user->name" size="sm" />
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $member->user->name }} @if ($member->user->is(auth()->user()))<span class="text-xs text-slate-400">(vous)</span>@endif</p>
                                        <p class="text-xs text-slate-500">{{ $member->user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($canManageMembers)
                                    <form method="POST" action="{{ route('organizers.members.update', [$organizer, $member]) }}">
                                        @csrf @method('PATCH')
                                        <select name="role" onchange="this.form.requestSubmit()" class="rounded-lg border-0 bg-slate-50 py-1.5 pr-8 pl-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-white/10">
                                            @foreach (OrganizerRole::options() as $value => $label)
                                                <option value="{{ $value }}" @selected($member->role->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <x-ui.badge :value="$member->role" />
                                @endif
                            </td>
                            <td class="text-slate-500">{{ $member->created_at?->translatedFormat('d M Y') }}</td>
                            <td class="text-right">
                                @if ($canManageMembers)
                                    <x-ui.confirm :action="route('organizers.members.destroy', [$organizer, $member])" method="DELETE"
                                        title="Retirer {{ $member->user->name }} ?" message="Cette personne n'aura plus accès à l'organisateur." confirm="Retirer">
                                        <x-ui.button size="sm" variant="ghost" icon="trash" class="!text-rose-600">Retirer</x-ui.button>
                                    </x-ui.confirm>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        </x-ui.tab-panel>

        <x-ui.tab-panel name="profile">
            <x-ui.card title="Profil public" description="Affiché aux artistes et au public dans l'application." icon="identification" class="max-w-3xl">
                <form method="POST" action="{{ route('organizers.update', $organizer) }}" enctype="multipart/form-data" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <fieldset @disabled(! $canEdit) class="contents">
                        <x-ui.input name="name" label="Nom" :value="$organizer->name" required />
                        <x-location-select :city="$organizer->city_id" :commune="$organizer->commune_id" :required="\App\Support\Locations::tree() !== []" />
                        <x-ui.textarea name="description" label="Description" :value="$organizer->description" class="sm:col-span-2" />
                        <x-ui.field label="Logo" class="sm:col-span-2">
                            <div class="flex items-center gap-4">
                                <x-ui.avatar :name="$organizer->name" :src="$organizer->logoUrl()" size="lg" square />
                                <input type="file" name="logo" accept="image/*" class="block text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-300">
                            </div>
                        </x-ui.field>
                    </fieldset>
                    @if ($canEdit)
                        <div class="flex justify-end sm:col-span-2"><x-ui.button type="submit" icon="check">Enregistrer</x-ui.button></div>
                    @endif
                </form>
            </x-ui.card>
        </x-ui.tab-panel>
    </x-ui.tabs>

    @if ($canManageMembers)
        <x-ui.modal name="add-member" title="Ajouter un manager" description="Si l'email n'a pas encore de compte, il est créé : la personne reçoit un mot de passe provisoire par SMS, à changer à sa première connexion." icon="user-plus" max-width="xl">
            <form method="POST" action="{{ route('organizers.members.store', $organizer) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="add-member">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" label="Nom complet" icon="user" hint="Obligatoire pour un nouveau compte." />
                    <x-ui.input name="email" type="email" label="Email de connexion" icon="envelope" required />
                </div>
                <x-phone-input label="Téléphone (nouveau compte)" :required="false" />
                <x-ui.select name="role" label="Rôle" :options="['staff' => 'Staff — inscriptions, matchs et soumissions', 'admin' => 'Administrateur — configure les compétitions et le jury']" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'add-member')">Annuler</x-ui.button>
                    <x-ui.button type="submit" icon="user-plus">Ajouter</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
    <x-realtime :channels="[\App\Realtime\Channel::organizer($organizer->id)]" />
</x-layouts.app>
