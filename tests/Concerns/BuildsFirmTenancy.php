<?php

namespace Tests\Concerns;

use App\Actions\Tenancy\CreateFirmMembership;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

trait BuildsFirmTenancy
{
    protected function createFirmMembership(
        Firm $firm,
        User $user,
        FirmRole $role = FirmRole::Preparer,
        FirmMembershipStatus $status = FirmMembershipStatus::Active,
    ): FirmMembership {
        return app(CreateFirmMembership::class)->handle($firm, $user, $role, $status);
    }

    protected function activateFirmMembership(FirmMembership $membership): void
    {
        app(FirmContext::class)->activateMembership($membership->load('firm'));
    }
}
