<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalFilterSurface: string
{
    case Dashboard = 'dashboard';
    case WorkRegister = 'work_register';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::WorkRegister => 'Work register',
        };
    }
}
