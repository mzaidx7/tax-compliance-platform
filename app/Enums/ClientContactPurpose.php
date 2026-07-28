<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientContactPurpose: string
{
    case Primary = 'primary';
    case Tax = 'tax';
    case Finance = 'finance';
    case AuthorizedSignatory = 'authorized_signatory';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary contact',
            self::Tax => 'Tax contact',
            self::Finance => 'Finance contact',
            self::AuthorizedSignatory => 'Authorized signatory',
        };
    }
}
