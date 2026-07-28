<?php

declare(strict_types=1);

namespace App\Enums;

enum AssignmentRole: string
{
    case Preparer = 'preparer';
    case Reviewer = 'reviewer';
    case ResponsibleManager = 'responsible_manager';

    public function label(): string
    {
        return match ($this) {
            self::Preparer => 'Preparer',
            self::Reviewer => 'Reviewer',
            self::ResponsibleManager => 'Responsible manager',
        };
    }
}
