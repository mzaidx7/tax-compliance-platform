<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\TaxRecord;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class TaxRecordPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageTaxRecords) ?? false;
    }

    public function view(User $user, TaxRecord $taxRecord): bool
    {
        return $this->managesTax($user, $taxRecord);
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageTaxRecords) ?? false;
    }

    public function amend(User $user, TaxRecord $taxRecord): bool
    {
        return $this->managesTax($user, $taxRecord);
    }

    public function update(User $user, TaxRecord $taxRecord): bool
    {
        return $this->managesTax($user, $taxRecord);
    }

    public function delete(User $user, TaxRecord $taxRecord): bool
    {
        return false;
    }

    public function restore(User $user, TaxRecord $taxRecord): bool
    {
        return false;
    }

    public function forceDelete(User $user, TaxRecord $taxRecord): bool
    {
        return false;
    }

    private function managesTax(User $user, TaxRecord $taxRecord): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $taxRecord->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ManageTaxRecords);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
