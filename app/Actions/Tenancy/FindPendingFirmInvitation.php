<?php

namespace App\Actions\Tenancy;

use App\Enums\FirmInvitationStatus;
use App\Enums\FirmStatus;
use App\Models\FirmInvitation;
use App\Models\Scopes\FirmScope;
use Illuminate\Validation\ValidationException;

class FindPendingFirmInvitation
{
    public function handle(string $plainTextToken): FirmInvitation
    {
        $invitation = FirmInvitation::withoutGlobalScope(FirmScope::class)
            ->with('firm')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (
            $invitation === null
            || $invitation->status !== FirmInvitationStatus::Pending
            || $invitation->expires_at->isPast()
            || $invitation->firm->status !== FirmStatus::Active
        ) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is invalid or has expired.',
            ]);
        }

        return $invitation;
    }
}
