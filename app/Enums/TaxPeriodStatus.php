<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
