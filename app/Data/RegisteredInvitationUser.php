<?php

namespace App\Data;

use App\Models\FirmMembership;
use App\Models\User;

final readonly class RegisteredInvitationUser
{
    public function __construct(
        public User $user,
        public FirmMembership $membership,
    ) {}
}
