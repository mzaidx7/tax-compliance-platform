<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientReminderCategory: string
{
    case Documents = 'documents';
    case Vat = 'vat';
    case CorporateTax = 'corporate_tax';

    public function label(): string
    {
        return match ($this) {
            self::Documents => 'Documents',
            self::Vat => 'VAT',
            self::CorporateTax => 'Corporate Tax',
        };
    }
}
