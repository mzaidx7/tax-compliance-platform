<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmMembershipStatus;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptFirmInvitation
{
    public function __construct(
        private FirmContext $firmContext,
        private FindPendingFirmInvitation $findPendingFirmInvitation,
        private CreateFirmMembership $createFirmMembership,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $user, string $plainTextToken): FirmMembership
    {
        return DB::transaction(function () use ($user, $plainTextToken): FirmMembership {
            $invitation = $this->findPendingFirmInvitation->handle($plainTextToken);
            $invitation->newQueryWithoutScopes()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals(Str::lower($invitation->email), Str::lower($user->email))) {
                throw ValidationException::withMessages([
                    'email' => 'This invitation was issued to a different email address.',
                ]);
            }

            $existingMembership = FirmMembership::withoutGlobalScope(FirmScope::class)
                ->where('firm_id', $invitation->firm_id)
                ->where('user_id', $user->getKey())
                ->first();

            if ($existingMembership !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'A membership already exists for this user and firm.',
                ]);
            }

            return $this->firmContext->runForFirm(
                $invitation->firm,
                function () use ($invitation, $user): FirmMembership {
                    $membership = $this->createFirmMembership->handle(
                        $invitation->firm,
                        $user,
                        $invitation->role,
                        FirmMembershipStatus::Active,
                    );

                    $invitation->forceFill([
                        'status' => FirmInvitationStatus::Accepted,
                        'accepted_by' => $user->getKey(),
                        'accepted_at' => now(),
                    ])->save();

                    $this->recordAudit->handle(
                        action: 'firm.invitation.accepted',
                        actor: $user,
                        auditable: $invitation,
                        before: ['status' => FirmInvitationStatus::Pending->value],
                        after: [
                            'status' => FirmInvitationStatus::Accepted->value,
                            'membership_id' => $membership->getKey(),
                        ],
                    );

                    return $membership;
                },
            );
        }, 3);
    }
}
