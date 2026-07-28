<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxRecordStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Final => 'Final',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Final => 'green',
        };
    }
}
