<?php

namespace App\Actions\Tenancy;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

class CreateFirmMembership
{
    public function __construct(private FirmContext $firmContext) {}

    public function handle(
        Firm $firm,
        User $user,
        FirmRole $role,
        FirmMembershipStatus $status = FirmMembershipStatus::Active,
    ): FirmMembership {
        return $this->firmContext->runForFirm($firm, function () use ($firm, $user, $role, $status): FirmMembership {
            $membership = new FirmMembership([
                'user_id' => $user->getKey(),
                'role' => $role,
                'status' => $status,
                'joined_at' => $status === FirmMembershipStatus::Active ? now() : null,
            ]);

            $membership->forceFill(['firm_id' => $firm->getKey()]);
            $membership->save();

            return $membership->refresh();
        });
    }
}
