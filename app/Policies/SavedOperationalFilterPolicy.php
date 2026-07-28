<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedOperationalFilter;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class SavedOperationalFilterPolicy
{
    public function __construct(private FirmContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->membership()?->user_id === $user->id;
    }

    public function view(User $user, SavedOperationalFilter $filter): bool
    {
        return $this->owns($user, $filter);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SavedOperationalFilter $filter): bool
    {
        return $this->owns($user, $filter);
    }

    public function delete(User $user, SavedOperationalFilter $filter): bool
    {
        return $this->owns($user, $filter);
    }

    private function owns(User $user, SavedOperationalFilter $filter): bool
    {
        return $filter->firm_id === $this->context->firmId()
            && $filter->user_id === $user->id
            && $this->viewAny($user);
    }
}
