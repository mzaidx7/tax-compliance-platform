<?php

namespace App\Data;

use App\Models\FirmInvitation;

final readonly class RotatedFirmInvitation
{
    public function __construct(
        public FirmInvitation $invitation,
        public string $plainTextToken,
    ) {}
}
