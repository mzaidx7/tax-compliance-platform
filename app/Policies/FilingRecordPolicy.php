<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FilingRecord;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class FilingRecordPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageFilings) ?? false;
    }

    public function view(User $user, FilingRecord $filingRecord): bool
    {
        return $this->managesFiling($user, $filingRecord);
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageFilings) ?? false;
    }

    public function transition(User $user, FilingRecord $filingRecord): bool
    {
        return $this->managesFiling($user, $filingRecord);
    }

    public function update(User $user, FilingRecord $filingRecord): bool
    {
        return $this->managesFiling($user, $filingRecord);
    }

    public function delete(User $user, FilingRecord $filingRecord): bool
    {
        return false;
    }

    public function restore(User $user, FilingRecord $filingRecord): bool
    {
        return false;
    }

    public function forceDelete(User $user, FilingRecord $filingRecord): bool
    {
        return false;
    }

    private function managesFiling(User $user, FilingRecord $filingRecord): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $filingRecord->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ManageFilings);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
