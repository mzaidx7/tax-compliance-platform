<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;

final readonly class WorkItemPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::AssignWork) ?? false;
    }

    public function view(User $user, WorkItem $workItem): bool
    {
        return $this->managesWorkItem($user, $workItem);
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::AssignWork) ?? false;
    }

    public function update(User $user, WorkItem $workItem): bool
    {
        return $this->managesWorkItem($user, $workItem);
    }

    public function transition(User $user, WorkItem $workItem): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $workItem->firm_id === $membership->firm_id
            && (
                $membership->hasPermission(Permission::PrepareWork)
                || $membership->hasPermission(Permission::ReviewWork)
                || $membership->hasPermission(Permission::AssignWork)
            );
    }

    public function review(User $user, WorkItem $workItem): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $workItem->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ReviewWork);
    }

    public function delete(User $user, WorkItem $workItem): bool
    {
        return false;
    }

    public function restore(User $user, WorkItem $workItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, WorkItem $workItem): bool
    {
        return false;
    }

    private function managesWorkItem(User $user, WorkItem $workItem): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $workItem->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::AssignWork);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
