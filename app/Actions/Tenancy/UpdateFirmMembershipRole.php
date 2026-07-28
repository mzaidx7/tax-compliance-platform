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

class UpdateFirmMembershipRole
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(
        User $actor,
        FirmMembership $membership,
        FirmRole $newRole,
        string $reason,
    ): FirmMembership {
        $validatedReason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $membership, $newRole, $validatedReason): FirmMembership {
            $lockedMembership = FirmMembership::query()
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('update', $lockedMembership);

            if ($lockedMembership->user_id === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'member' => 'You cannot change your own firm role.',
                ]);
            }

            if ($lockedMembership->status === FirmMembershipStatus::Revoked) {
                throw ValidationException::withMessages([
                    'member' => 'A revoked membership cannot be changed.',
                ]);
            }

            if ($lockedMembership->role === $newRole) {
                throw ValidationException::withMessages([
                    'selectedRole' => 'Choose a different role.',
                ]);
            }

            if (
                $lockedMembership->role === FirmRole::FirmAdministrator
                && $lockedMembership->status === FirmMembershipStatus::Active
                && $newRole !== FirmRole::FirmAdministrator
            ) {
                $this->assertAnotherActiveAdministratorExists();
            }

            $previousRole = $lockedMembership->role;
            $lockedMembership->forceFill(['role' => $newRole])->save();

            $this->recordAudit->handle(
                action: 'firm.membership.role_changed',
                actor: $actor,
                auditable: $lockedMembership,
                before: ['role' => $previousRole->value],
                after: ['role' => $newRole->value],
                reason: $validatedReason,
            );

            return $lockedMembership->refresh();
        }, 3);
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
                'member' => 'Assign another active firm administrator before changing this role.',
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
