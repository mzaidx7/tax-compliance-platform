<?php

namespace Tests\Fixtures\Livewire;

use App\Models\FirmMembership;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MembershipLookup extends Component
{
    public ?string $revealedEmail = null;

    public function revealMembership(string $membershipId): void
    {
        $membership = FirmMembership::query()
            ->with('user')
            ->findOrFail($membershipId);

        $this->revealedEmail = $membership->user->email;
    }

    public function render(): View
    {
        return view('tenancy-tests::membership-lookup');
    }
}
