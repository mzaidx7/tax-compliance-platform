<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FeatureFlagOverride;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

/**
 * Feature-flag administration is restricted to a firm administrator through the
 * named manage_firm_settings permission. There is no deletion path; an override
 * is changed, never removed.
 */
final readonly class FeatureFlagOverridePolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageFirmSettings) ?? false;
    }

    public function update(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageFirmSettings) ?? false;
    }

    public function delete(User $user, FeatureFlagOverride $override): bool
    {
        return false;
    }

    public function restore(User $user, FeatureFlagOverride $override): bool
    {
        return false;
    }

    public function forceDelete(User $user, FeatureFlagOverride $override): bool
    {
        return false;
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
