<?php

declare(strict_types=1);

namespace App\Enums;

enum PartyFieldKey: string
{
    case LegalName = 'legal_name';
    case Trn = 'trn';
    case AddressLine = 'address_line';
    case City = 'city';
    case Emirate = 'emirate';
    case Country = 'country';
    case Email = 'email';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::LegalName => 'Legal name',
            self::Trn => 'TRN',
            self::AddressLine => 'Address line',
            default => ucfirst($this->value),
        };
    }
}
