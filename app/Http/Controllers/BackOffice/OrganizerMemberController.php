<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\InviteUserRequest;
use App\Models\Organizer;
use App\Models\OrganizerMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * $member is resolved through $organizer->members() (scoped binding).
 */
class OrganizerMemberController extends Controller
{
    public function store(InviteUserRequest $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('manageMembers', $organizer);

        $user = $request->invitedUser();

        if ($user->isMemberOf($organizer)) {
            throw ValidationException::withMessages(['phone' => 'Cet utilisateur est déjà membre.']);
        }

        $organizer->users()->attach($user, ['role' => $request->enum('role', OrganizerRole::class) ?? OrganizerRole::Staff]);

        return back()->with('status', 'Membre ajouté.');
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
