<?php

namespace App\Actions\Tenancy;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Http\Middleware\ResolveFirmContext;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;

class SwitchActiveFirm
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $user, string $firmId, Session $session): FirmMembership
    {
        $membership = FirmMembership::withoutGlobalScope(FirmScope::class)
            ->with('firm')
            ->where('user_id', $user->getKey())
            ->where('firm_id', $firmId)
            ->where('status', FirmMembershipStatus::Active)
            ->whereHas('firm', fn ($query) => $query->where('status', FirmStatus::Active))
            ->first();

        if ($membership === null) {
            throw new AuthorizationException('The selected firm is not available.');
        }

        $this->firmContext->activateMembership($membership);
        $session->put(ResolveFirmContext::SESSION_KEY, $membership->firm_id);

        $this->recordAudit->handle(
            action: 'firm.context.switched',
            actor: $user,
            auditable: $membership->firm,
            after: ['firm_id' => $membership->firm_id],
        );

        return $membership;
    }
}
