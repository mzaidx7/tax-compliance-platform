<?php

declare(strict_types=1);

namespace App\Enums;

enum PreferredContactChannel: string
{
    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
