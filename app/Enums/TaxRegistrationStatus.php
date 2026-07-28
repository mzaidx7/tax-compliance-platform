<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxRegistrationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Deregistered = 'deregistered';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
