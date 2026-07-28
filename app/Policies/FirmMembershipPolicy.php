<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

class FirmMembershipPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageMembers) ?? false;
    }

    public function view(User $user, FirmMembership $firmMembership): bool
    {
        $actorMembership = $this->actorMembership($user);

        return $actorMembership !== null
            && $firmMembership->firm_id === $actorMembership->firm_id
            && (
                $firmMembership->user_id === $user->getKey()
                || $actorMembership->hasPermission(Permission::ManageMembers)
            );
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageMembers) ?? false;
    }

    public function update(User $user, FirmMembership $firmMembership): bool
    {
        return $this->managesTarget($user, $firmMembership);
    }

    public function delete(User $user, FirmMembership $firmMembership): bool
    {
        return $this->managesTarget($user, $firmMembership);
    }

    public function restore(User $user, FirmMembership $firmMembership): bool
    {
        return false;
    }

    public function forceDelete(User $user, FirmMembership $firmMembership): bool
    {
        return false;
    }

    private function managesTarget(User $user, FirmMembership $target): bool
    {
        $actorMembership = $this->actorMembership($user);

        return $actorMembership !== null
            && $target->firm_id === $actorMembership->firm_id
            && $actorMembership->hasPermission(Permission::ManageMembers);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
