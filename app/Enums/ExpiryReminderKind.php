<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpiryReminderKind: string
{
    case Upcoming = 'upcoming';
    case ExpiryDay = 'expiry_day';
    case OverdueEscalation = 'overdue_escalation';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming expiry',
            self::ExpiryDay => 'Expires today',
            self::OverdueEscalation => 'Overdue escalation',
        };
    }
}
