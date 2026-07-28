<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DataQualityRuleDefinition;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class DataQualityRuleDefinitionPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->membershipAllows($user);
    }

    public function create(User $user): bool
    {
        return $this->membershipAllows($user);
    }

    public function update(User $user, DataQualityRuleDefinition $definition): bool
    {
        return $definition->firm_id === $this->firmContext->firmId() && $this->membershipAllows($user);
    }

    public function delete(User $user, DataQualityRuleDefinition $definition): bool
    {
        return false;
    }

    private function membershipAllows(User $user): bool
    {
        $membership = $this->firmContext->membership();

        return $membership !== null
            && $membership->user_id === $user->id
            && $membership->hasPermission(Permission::ManageReadinessRules);
    }
}
