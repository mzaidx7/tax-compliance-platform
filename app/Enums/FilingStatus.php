<?php

declare(strict_types=1);

namespace App\Enums;

enum FilingStatus: string
{
    case NotRequired = 'not_required';
    case NotFiled = 'not_filed';
    case Filed = 'filed';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';
    case Corrected = 'corrected';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::NotFiled => 'Not filed',
            self::Filed => 'Filed',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
            self::Corrected => 'Corrected',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::NotRequired => 'zinc',
            self::NotFiled => 'amber',
            self::Filed, self::Corrected => 'blue',
            self::Acknowledged => 'green',
            self::Rejected => 'red',
        };
    }

    /**
     * Filing state moves independently of work state and payment state.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotRequired => [self::NotFiled],
            self::NotFiled => [self::Filed, self::NotRequired],
            self::Filed => [self::Acknowledged, self::Rejected],
            self::Acknowledged => [self::Corrected],
            self::Rejected => [self::Corrected, self::Filed],
            self::Corrected => [self::Acknowledged, self::Rejected],
        };
    }

    /**
     * Statuses that assert a submission reached the authority.
     */
    public function requiresFilingReference(): bool
    {
        return match ($this) {
            self::Filed, self::Acknowledged, self::Rejected, self::Corrected => true,
            self::NotRequired, self::NotFiled => false,
        };
    }
}
