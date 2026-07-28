<?php

namespace App\Http\Middleware;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use App\Tenancy\FirmContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ResolveFirmContext
{
    public const SESSION_KEY = 'active_firm_id';

    public function __construct(private FirmContext $firmContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $membershipQuery = FirmMembership::withoutGlobalScope(FirmScope::class)
            ->with('firm')
            ->where('user_id', $user->getKey())
            ->where('status', FirmMembershipStatus::Active)
            ->whereHas('firm', fn ($query) => $query->where('status', FirmStatus::Active));

        $selectedFirmId = $request->session()->get(self::SESSION_KEY);

        if ($selectedFirmId !== null) {
            if (! is_string($selectedFirmId)) {
                $request->session()->forget(self::SESSION_KEY);
                abort(Response::HTTP_FORBIDDEN, 'The selected firm is not available.');
            }

            $membership = (clone $membershipQuery)
                ->where('firm_id', $selectedFirmId)
                ->first();

            if ($membership === null) {
                $request->session()->forget(self::SESSION_KEY);

                if (! $request->expectsJson() && (clone $membershipQuery)->exists()) {
                    return redirect()->route('firms.select');
                }

                abort(Response::HTTP_FORBIDDEN, 'The selected firm is not available.');
            }
        } else {
            /** @var Collection<int, FirmMembership> $memberships */
            $memberships = $membershipQuery->limit(2)->get();

            if ($memberships->isEmpty()) {
                abort(Response::HTTP_FORBIDDEN, 'An active firm membership is required.');
            }

            if ($memberships->count() > 1) {
                if (! $request->expectsJson()) {
                    return redirect()->route('firms.select');
                }

                abort(Response::HTTP_CONFLICT, 'Choose an active firm before continuing.');
            }

            $membership = $memberships->sole();
            $request->session()->put(self::SESSION_KEY, $membership->firm_id);
        }

        $this->firmContext->activateMembership($membership);

        return $next($request);
    }
}
