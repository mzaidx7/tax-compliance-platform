<?php

namespace App\Data;

use App\Models\FirmInvitation;

final readonly class CreatedFirmInvitation
{
    public function __construct(
        public FirmInvitation $invitation,
        public string $plainTextToken,
    ) {}
}
