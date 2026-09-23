<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * What a member can do inside an organizer. Granted through OrganizerRole::permissions().
 */
enum OrganizerPermission: string
{
    use EnumHelpers;

    case ViewCompetitions = 'competitions.view';
    case ManageCompetitions = 'competitions.manage';
    case DeleteCompetitions = 'competitions.delete';
    case ManageRegistrations = 'registrations.manage';
    case RunMatches = 'matches.run';
    case EditProfile = 'organizer.edit';
    case ManageMembers = 'members.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewCompetitions => 'Voir les compétitions',
            self::ManageCompetitions => 'Gérer les compétitions, phases, jury et critères',
            self::DeleteCompetitions => 'Supprimer des compétitions',
            self::ManageRegistrations => 'Valider les inscriptions',
            self::RunMatches => 'Gérer les matchs et les performances',
            self::EditProfile => "Modifier le profil de l'organisateur",
            self::ManageMembers => 'Gérer les membres',
        };
    }
}
