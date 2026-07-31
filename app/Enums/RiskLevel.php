<?php

declare(strict_types=1);

namespace App\Enums;

enum RiskLevel: string
{
    case Unassessed = 'unassessed';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Unassessed => 'Not set',
            self::Low => 'Routine',
            self::Medium => 'Needs attention',
            self::High => 'Urgent',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Unassessed => 'zinc',
            self::Low => 'green',
            self::Medium => 'amber',
            self::High => 'red',
        };
    }
}
