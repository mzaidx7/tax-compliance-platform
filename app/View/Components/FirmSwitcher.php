<?php

namespace App\View\Components;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class FirmSwitcher extends Component
{
    public FirmMembership $currentMembership;

    /**
     * @var Collection<int, FirmMembership>
     */
    public Collection $memberships;

    public function __construct(FirmContext $firmContext)
    {
        $currentMembership = $firmContext->membership();
        $user = Auth::user();

        abort_unless($currentMembership !== null && $user instanceof User, 403);

        $this->currentMembership = $currentMembership->loadMissing('firm');
        $this->memberships = FirmMembership::withoutGlobalScope(FirmScope::class)
            ->with('firm')
            ->where('user_id', $user->getKey())
            ->where('status', FirmMembershipStatus::Active)
            ->whereHas('firm', fn ($query) => $query->where('status', FirmStatus::Active))
            ->orderBy('created_at')
            ->get();
    }

    public function render(): View
    {
        return view('components.firm-switcher');
    }
}
