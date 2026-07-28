<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceEnrollmentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
