<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Unknown => 'Unknown',
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::NotRequired => 'zinc',
            self::Unknown => 'purple',
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Overdue => 'red',
        };
    }

    /**
     * Payment state moves independently of work state and filing state.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotRequired => [self::Unknown, self::Pending],
            self::Unknown => [self::Pending, self::NotRequired],
            self::Pending => [self::Paid, self::Overdue, self::NotRequired],
            self::Overdue => [self::Paid, self::Pending],
            self::Paid => [],
        };
    }

    /**
     * States that assert money actually moved and therefore need retained evidence.
     */
    public function requiresPaymentEvidence(): bool
    {
        return $this === self::Paid;
    }
}
