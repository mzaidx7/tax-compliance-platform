<?php

declare(strict_types=1);

namespace App\Enums;

enum PartyFieldVerificationState: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
