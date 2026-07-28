<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class ObligationPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->canViewOperations($user);
    }

    public function view(User $user, Obligation $obligation): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $obligation->firm_id === $membership->firm_id
            && $this->canViewOperations($user);
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageObligations) ?? false;
    }

    public function update(User $user, Obligation $obligation): bool
    {
        return $this->managesObligation($user, $obligation);
    }

    public function delete(User $user, Obligation $obligation): bool
    {
        return false;
    }

    public function restore(User $user, Obligation $obligation): bool
    {
        return false;
    }

    public function forceDelete(User $user, Obligation $obligation): bool
    {
        return false;
    }

    private function managesObligation(User $user, Obligation $obligation): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $obligation->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ManageObligations);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }

    private function canViewOperations(User $user): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null && (
            $membership->hasPermission(Permission::ManageObligations)
            || $membership->hasPermission(Permission::PrepareWork)
            || $membership->hasPermission(Permission::ReviewWork)
        );
    }
}
