<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Data\RotatedFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\Permission;
use App\Models\FirmInvitation;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RotateFirmInvitation
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, FirmInvitation $invitation): RotatedFirmInvitation
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $invitation): RotatedFirmInvitation {
            $lockedInvitation = FirmInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->status !== FirmInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only a pending invitation can be resent.',
                ]);
            }

            $previousExpiry = $lockedInvitation->expires_at->toIso8601String();
            $plainTextToken = bin2hex(random_bytes(32));
            $lockedInvitation->forceFill([
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now()->addHours(72),
            ])->save();

            $this->recordAudit->handle(
                action: 'firm.invitation.resent',
                actor: $actor,
                auditable: $lockedInvitation,
                before: ['expires_at' => $previousExpiry],
                after: ['expires_at' => $lockedInvitation->expires_at->toIso8601String()],
            );

            return new RotatedFirmInvitation(
                invitation: $lockedInvitation->refresh(),
                plainTextToken: $plainTextToken,
            );
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
