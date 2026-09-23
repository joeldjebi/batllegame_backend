<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Payment methods offered on the (simulated) checkout.
 */
enum PaymentMethod: string
{
    use EnumHelpers;

    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Wave = 'wave';
    case Card = 'carte';

    public function label(): string
    {
        return match ($this) {
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Wave => 'Wave',
            self::Card => 'Carte bancaire',
        };
    }

    public function icon(): string
    {
        return $this === self::Card ? 'credit-card' : 'device-phone-mobile';
    }
}
