<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Tenancy\FirmContext;

final readonly class PaymentRecordPolicy
{
    public function __construct(private FirmContext $firmContext) {}

    public function viewAny(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManagePayments) ?? false;
    }

    public function view(User $user, PaymentRecord $paymentRecord): bool
    {
        return $this->managesPayment($user, $paymentRecord);
    }

    public function create(User $user): bool
    {
        return $this->actorMembership($user)?->hasPermission(Permission::ManagePayments) ?? false;
    }

    public function transition(User $user, PaymentRecord $paymentRecord): bool
    {
        return $this->managesPayment($user, $paymentRecord);
    }

    public function update(User $user, PaymentRecord $paymentRecord): bool
    {
        return $this->managesPayment($user, $paymentRecord);
    }

    public function delete(User $user, PaymentRecord $paymentRecord): bool
    {
        return false;
    }

    public function restore(User $user, PaymentRecord $paymentRecord): bool
    {
        return false;
    }

    public function forceDelete(User $user, PaymentRecord $paymentRecord): bool
    {
        return false;
    }

    private function managesPayment(User $user, PaymentRecord $paymentRecord): bool
    {
        $membership = $this->actorMembership($user);

        return $membership !== null
            && $paymentRecord->firm_id === $membership->firm_id
            && $membership->hasPermission(Permission::ManagePayments);
    }

    private function actorMembership(User $user): ?FirmMembership
    {
        $membership = $this->firmContext->membership();

        return $membership !== null && $membership->user_id === $user->getKey()
            ? $membership
            : null;
    }
}
