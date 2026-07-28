<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ChecklistVersion;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class ChecklistVersionPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, ChecklistVersion $version): bool
    {
        return $version->firm_id === $this->firmContext->firmId() && $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ChecklistVersion $version): bool
    {
        return false;
    }

    public function delete(User $user, ChecklistVersion $version): bool
    {
        return false;
    }

    public function restore(User $user, ChecklistVersion $version): bool
    {
        return false;
    }

    public function forceDelete(User $user, ChecklistVersion $version): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        $membership = $this->firmContext->membership();

        return $membership instanceof FirmMembership
            && $membership->user_id === $user->id
            && $membership->hasPermission(Permission::AssignWork);
    }
}
