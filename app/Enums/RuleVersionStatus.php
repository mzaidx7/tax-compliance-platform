<?php

declare(strict_types=1);

namespace App\Enums;

enum RuleVersionStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Published = 'published';
    case Superseded = 'superseded';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::UnderReview => 'Under review',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Superseded => 'Superseded',
            self::Retired => 'Retired',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::UnderReview => 'amber',
            self::Approved => 'blue',
            self::Published => 'green',
            self::Superseded => 'purple',
            self::Retired => 'red',
        };
    }
}
