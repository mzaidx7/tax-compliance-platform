<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateFirmMembershipStatus
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(
        User $actor,
        FirmMembership $membership,
        FirmMembershipStatus $newStatus,
        string $reason,
    ): FirmMembership {
        $validatedReason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $membership, $newStatus, $validatedReason): FirmMembership {
            $lockedMembership = FirmMembership::query()
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('update', $lockedMembership);

            if ($lockedMembership->user_id === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'member' => 'You cannot change your own firm access.',
                ]);
            }

            $this->assertTransitionAllowed($lockedMembership->status, $newStatus);

            if (
                $lockedMembership->role === FirmRole::FirmAdministrator
                && $lockedMembership->status === FirmMembershipStatus::Active
                && $newStatus !== FirmMembershipStatus::Active
            ) {
                $this->assertAnotherActiveAdministratorExists();
            }

            $previousStatus = $lockedMembership->status;
            $lockedMembership->forceFill([
                'status' => $newStatus,
                'suspended_at' => $newStatus === FirmMembershipStatus::Suspended ? now() : null,
                'revoked_at' => $newStatus === FirmMembershipStatus::Revoked ? now() : null,
            ])->save();

            $this->recordAudit->handle(
                action: match ($newStatus) {
                    FirmMembershipStatus::Active => 'firm.membership.reactivated',
                    FirmMembershipStatus::Suspended => 'firm.membership.suspended',
                    FirmMembershipStatus::Revoked => 'firm.membership.revoked',
                    FirmMembershipStatus::Invited => throw new \LogicException('Invited is not a membership lifecycle action.'),
                },
                actor: $actor,
                auditable: $lockedMembership,
                before: ['status' => $previousStatus->value],
                after: ['status' => $newStatus->value],
                reason: $validatedReason,
            );

            return $lockedMembership->refresh();
        }, 3);
    }

    private function assertTransitionAllowed(
        FirmMembershipStatus $currentStatus,
        FirmMembershipStatus $newStatus,
    ): void {
        $isAllowed = match ($currentStatus) {
            FirmMembershipStatus::Active => in_array(
                $newStatus,
                [FirmMembershipStatus::Suspended, FirmMembershipStatus::Revoked],
                true,
            ),
            FirmMembershipStatus::Suspended => in_array(
                $newStatus,
                [FirmMembershipStatus::Active, FirmMembershipStatus::Revoked],
                true,
            ),
            FirmMembershipStatus::Invited, FirmMembershipStatus::Revoked => false,
        };

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                'member' => 'This membership status change is not allowed.',
            ]);
        }
    }

    private function assertAnotherActiveAdministratorExists(): void
    {
        $administratorIds = FirmMembership::query()
            ->where('role', FirmRole::FirmAdministrator)
            ->where('status', FirmMembershipStatus::Active)
            ->lockForUpdate()
            ->pluck('id');

        if ($administratorIds->count() <= 1) {
            throw ValidationException::withMessages([
                'member' => 'Assign another active firm administrator before disabling this membership.',
            ]);
        }
    }

    private function validatedReason(string $reason): string
    {
        /** @var array{reason: string} $validated */
        $validated = Validator::make(
            ['reason' => trim($reason)],
            ['reason' => ['required', 'string', 'max:500']],
        )->validate();

        return $validated['reason'];
    }
}
