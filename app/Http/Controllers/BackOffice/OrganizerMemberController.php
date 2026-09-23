<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\InviteMemberRequest;
use App\Models\Organizer;
use App\Models\OrganizerMember;
use App\Services\BackOfficeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * $member is resolved through $organizer->members() (scoped binding).
 */
class OrganizerMemberController extends Controller
{
    public function store(InviteMemberRequest $request, Organizer $organizer, BackOfficeAccountService $accounts): RedirectResponse
    {
        $this->authorize('manageMembers', $organizer);

        [$user, $password] = $accounts->findOrCreate(
            $request->validated('email'),
            $request->validated('name'),
            $request->country(),
            $request->validated('phone'),
            $organizer->name,
        );

        if ($user->isPlatformAdmin()) {
            throw ValidationException::withMessages(['email' => 'Ce compte ne peut pas être membre d\'un organisateur.']);
        }

        if ($user->isMemberOf($organizer)) {
            throw ValidationException::withMessages(['email' => 'Cette personne est déjà membre.']);
        }

        $organizer->users()->attach($user, ['role' => $request->enum('role', OrganizerRole::class)]);

        return back()->with('status', $password
            ? "Compte créé pour {$user->name}. Mot de passe provisoire : {$password} (envoyé par SMS, à changer à la première connexion)."
            : "{$user->name} a été ajouté à l'équipe.");
    }

    public function update(Request $request, Organizer $organizer, OrganizerMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organizer);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(OrganizerRole::class)],
        ]);

        $this->ensureAnOwnerRemains($organizer, $member, OrganizerRole::from($validated['role']));
        $member->update(['role' => $validated['role']]);

        return back()->with('status', 'Rôle mis à jour.');
    }

    public function destroy(Organizer $organizer, OrganizerMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organizer);

        $this->ensureAnOwnerRemains($organizer, $member, null);
        $member->delete();

        return back()->with('status', 'Membre retiré.');
    }

    private function ensureAnOwnerRemains(Organizer $organizer, OrganizerMember $member, ?OrganizerRole $newRole): void
    {
        $losesOwner = $member->role === OrganizerRole::Owner && $newRole !== OrganizerRole::Owner;

        if ($losesOwner && $organizer->members()->where('role', OrganizerRole::Owner)->count() === 1) {
            throw ValidationException::withMessages(['role' => "L'organisateur doit garder au moins un propriétaire."]);
        }
    }
}
