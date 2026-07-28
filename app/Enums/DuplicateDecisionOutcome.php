<?php

declare(strict_types=1);

namespace App\Enums;

enum DuplicateDecisionOutcome: string
{
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed duplicate',
            self::Dismissed => 'Dismissed',
        };
    }
}
