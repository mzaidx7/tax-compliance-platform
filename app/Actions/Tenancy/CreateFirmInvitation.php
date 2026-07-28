<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Data\CreatedFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmRole;
use App\Enums\Permission;
use App\Models\FirmInvitation;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateFirmInvitation
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, string $email, FirmRole $role): CreatedFirmInvitation
    {
        $membership = $this->firmContext->membership();

        if (
            $membership === null
            || $membership->user_id !== $actor->getKey()
            || ! $membership->hasPermission(Permission::InviteMembers)
        ) {
            throw new AuthorizationException('You cannot invite members to this firm.');
        }

        $validated = Validator::make(
            ['email' => Str::lower(trim($email))],
            ['email' => ['required', 'string', 'email:rfc', 'max:255']],
        )->validate();

        $normalizedEmail = $validated['email'];
        $existingUser = User::query()->where('email', $normalizedEmail)->first();

        if (
            $existingUser !== null
            && FirmMembership::withoutGlobalScope(FirmScope::class)
                ->where('firm_id', $this->firmContext->firm()->getKey())
                ->where('user_id', $existingUser->getKey())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => 'A membership already exists for this person. Manage the existing access record instead.',
            ]);
        }

        if (
            FirmInvitation::query()
                ->where('email', $normalizedEmail)
                ->where('status', FirmInvitationStatus::Pending)
                ->where('expires_at', '>', now())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email address.',
            ]);
        }

        $plainTextToken = bin2hex(random_bytes(32));
        $invitation = FirmInvitation::query()->create([
            'email' => $normalizedEmail,
            'role' => $role,
            'status' => FirmInvitationStatus::Pending,
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addHours(72),
            'invited_by' => $actor->getKey(),
        ]);

        $this->recordAudit->handle(
            action: 'firm.invitation.created',
            actor: $actor,
            auditable: $invitation,
            after: [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        );

        return new CreatedFirmInvitation($invitation, $plainTextToken);
    }
}
