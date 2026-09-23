<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * State of a participant within a competition.
 */
enum ParticipantStatus: string implements HasBadge
{
    use EnumHelpers;

    case PaymentPending = 'paiement_en_attente';
    case Registered = 'inscrit';
    case Validated = 'valide';
    case Eliminated = 'elimine';
    case Withdrawn = 'forfait';
    case Disqualified = 'disqualifie';
    case NotSelected = 'non_retenu';

    public function label(): string
    {
        return match ($this) {
            self::PaymentPending => 'Paiement en attente',
            self::Registered => 'Inscrit',
            self::Validated => 'Validé',
            self::Eliminated => 'Éliminé',
            self::Withdrawn => 'Forfait',
            self::Disqualified => 'Disqualifié',
            self::NotSelected => 'Non retenu',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PaymentPending => 'red',
            self::Registered => 'amber',
            self::Validated => 'green',
            self::Eliminated => 'gray',
            self::Withdrawn => 'gray',
            self::Disqualified => 'red',
            self::NotSelected => 'gray',
        };
    }
}
