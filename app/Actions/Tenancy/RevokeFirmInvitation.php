<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmInvitationStatus;
use App\Enums\Permission;
use App\Models\FirmInvitation;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RevokeFirmInvitation
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, FirmInvitation $invitation, string $reason): FirmInvitation
    {
        $this->authorize($actor);

        /** @var array{reason: string} $validated */
        $validated = Validator::make(
            ['reason' => trim($reason)],
            ['reason' => ['required', 'string', 'max:500']],
        )->validate();

        return DB::transaction(function () use ($actor, $invitation, $validated): FirmInvitation {
            $lockedInvitation = FirmInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->status !== FirmInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only a pending invitation can be revoked.',
                ]);
            }

            $lockedInvitation->forceFill([
                'status' => FirmInvitationStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            $this->recordAudit->handle(
                action: 'firm.invitation.revoked',
                actor: $actor,
                auditable: $lockedInvitation,
                before: ['status' => FirmInvitationStatus::Pending->value],
                after: ['status' => FirmInvitationStatus::Revoked->value],
                reason: $validated['reason'],
            );

            return $lockedInvitation->refresh();
        }, 3);
    }

    private function authorize(User $actor): void
    {
        $membership = $this->firmContext->membership();

        if (
            $membership === null
            || $membership->user_id !== $actor->getKey()
            || ! $membership->hasPermission(Permission::InviteMembers)
        ) {
            throw new AuthorizationException('You cannot manage invitations for this firm.');
        }
    }
}
