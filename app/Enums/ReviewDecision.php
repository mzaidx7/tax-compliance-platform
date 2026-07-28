<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewDecision: string
{
    case Approve = 'approve';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Approve',
            self::Return => 'Return for changes',
        };
    }

    public function targetStatus(): WorkItemStatus
    {
        return match ($this) {
            self::Approve => WorkItemStatus::AwaitingClientApproval,
            self::Return => WorkItemStatus::ReturnedForChanges,
        };
    }
}
