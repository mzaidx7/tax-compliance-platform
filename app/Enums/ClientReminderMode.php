<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientReminderMode: string
{
    case Off = 'off';
    case Review = 'review';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Review => 'Review before sending',
            self::Automatic => 'Send automatically',
        };
    }
}
