<?php

declare(strict_types=1);

namespace App\Enums;

enum ReadinessDataDomain: string
{
    case PartyMaster = 'party_master';
    case InvoiceTransaction = 'invoice_transaction';

    public function label(): string
    {
        return match ($this) {
            self::PartyMaster => 'Party master',
            self::InvoiceTransaction => 'Invoice transaction',
        };
    }
}
