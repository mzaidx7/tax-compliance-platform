<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class ClientPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageClients) ?? false;
    }

    public function view(User $user, Client $client): bool
    {
        $membership = $this->actorMembership($user);
        if ($membership === null || $client->firm_id !== $membership->firm_id) {
            return false;
        }

        if (
            $membership->hasPermission(Permission::ManageClients)
            || $membership->hasPermission(Permission::ManageObligations)
            || $membership->hasPermission(Permission::AssignWork)
            || $membership->hasPermission(Permission::ViewReports)
        ) {
            return true;
        }

        return $client->obligations()
            ->whereHas(
                'workItems.assignmentHistories',
                static fn ($query) => $query->where('assigned_membership_id', $membership->id),
            )
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManageClients) ?? false;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->managesClient($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return false;
    }

    public function restore(User $user, Client $client): bool
    {
        return false;
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return false;
    }

    private function managesClient(User $user, Client $client): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $client->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ManageClients);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
