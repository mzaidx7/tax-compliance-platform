<?php

namespace App\Http\Controllers;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FirmSelectionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $memberships = FirmMembership::withoutGlobalScope(FirmScope::class)
            ->with('firm')
            ->where('user_id', $user->getKey())
            ->where('status', FirmMembershipStatus::Active)
            ->whereHas('firm', fn ($query) => $query->where('status', FirmStatus::Active))
            ->orderBy('created_at')
            ->get();

        abort_if($memberships->isEmpty(), 403, 'An active firm membership is required.');

        return view('firms.select', ['memberships' => $memberships]);
    }
}
