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
            self::Unassessed => 'Unassessed',
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
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
