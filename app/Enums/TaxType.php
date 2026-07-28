<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxType: string
{
    case Vat = 'vat';
    case CorporateTax = 'corporate_tax';
    case ExciseTax = 'excise_tax';

    public function label(): string
    {
        return match ($this) {
            self::Vat => 'VAT',
            self::CorporateTax => 'Corporate tax',
            self::ExciseTax => 'Excise tax',
        };
    }
}
