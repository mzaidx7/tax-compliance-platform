<?php

declare(strict_types=1);

namespace App\Enums;

enum ObligationOrigin: string
{
    case Manual = 'manual';
    case GovernedRule = 'governed_rule';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual entry',
            self::GovernedRule => 'Governed rule',
        };
    }
}
