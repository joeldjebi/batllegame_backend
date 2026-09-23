<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * State of a registration fee payment (simulated until a provider is plugged in).
 */
enum PaymentStatus: string implements HasBadge
{
    use EnumHelpers;

    case Pending = 'en_attente';
    case Paid = 'payee';
    case Failed = 'echouee';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Paid => 'Payé',
            self::Failed => 'Échoué',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Failed => 'red',
        };
    }
}
