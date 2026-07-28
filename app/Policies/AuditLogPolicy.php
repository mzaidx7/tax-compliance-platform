<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

/**
 * Audit history is readable evidence only. Every write ability is denied here so
 * no interface path can ever create, alter or remove an audit record.
 */
final readonly class AuditLogPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ViewAuditLog) ?? false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $auditLog->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ViewAuditLog);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function restore(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
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
