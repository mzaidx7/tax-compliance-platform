<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationRunStatus: string
{
    case Preview = 'preview';
    case Committed = 'committed';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'Preview',
            self::Committed => 'Committed',
        };
    }
}
