<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Disengaged = 'disengaged';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Disengaged => 'Disengaged',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'zinc',
            self::Disengaged => 'amber',
        };
    }
}
