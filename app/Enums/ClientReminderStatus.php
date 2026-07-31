<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientReminderStatus: string
{
    case AwaitingReview = 'awaiting_review';
    case Queued = 'queued';
    case Sent = 'sent';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingReview => 'Awaiting review',
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Blocked => 'Blocked',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
