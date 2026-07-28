<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PartyRecord;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class PartyRecordPolicy
{
    public function __construct(private FirmContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, [Permission::ManageImports, Permission::ManageReadinessRules]);
    }

    public function view(User $user, PartyRecord $party): bool
    {
        return $party->firm_id === $this->context->firmId() && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, [Permission::ManageImports]);
    }

    public function update(User $user, PartyRecord $party): bool
    {
        return $party->firm_id === $this->context->firmId() && $this->create($user);
    }

    public function approveCorrection(User $user, PartyRecord $party): bool
    {
        return $party->firm_id === $this->context->firmId() && $this->allows($user, [Permission::ManageReadinessRules]);
    }

    public function delete(User $user, PartyRecord $party): bool
    {
        return false;
    }

    /** @param list<Permission> $permissions */
    private function allows(User $user, array $permissions): bool
    {
        $membership = $this->context->membership();
        if ($membership === null || $membership->user_id !== $user->id) {
            return false;
        }

        return collect($permissions)->contains(fn (Permission $permission): bool => $membership->hasPermission($permission));
    }
}
