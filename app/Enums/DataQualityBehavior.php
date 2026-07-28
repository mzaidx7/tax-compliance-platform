<?php

declare(strict_types=1);

namespace App\Enums;

enum DataQualityBehavior: string
{
    case Warning = 'warning';
    case Blocking = 'blocking';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
