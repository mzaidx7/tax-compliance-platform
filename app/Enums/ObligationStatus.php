<?php

declare(strict_types=1);

namespace App\Enums;

enum ObligationStatus: string
{
    case Open = 'open';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Superseded => 'Superseded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Superseded => 'zinc',
            self::Cancelled => 'red',
        };
    }
}
