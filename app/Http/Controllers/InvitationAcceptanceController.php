<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\AcceptFirmInvitation;
use App\Actions\Tenancy\FindPendingFirmInvitation;
use App\Actions\Tenancy\RegisterInvitedUser;
use App\Http\Middleware\ResolveFirmContext;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationAcceptanceController extends Controller
{
    public function show(
        Request $request,
        string $token,
        FindPendingFirmInvitation $findPendingFirmInvitation,
    ): View {
        $invitation = $findPendingFirmInvitation->handle($token);
        $user = $request->user();

        if ($user instanceof User && strcasecmp($user->email, $invitation->email) !== 0) {
            abort(403, 'This invitation was issued to a different email address.');
        }

        $hasExistingAccount = User::query()
            ->where('email', $invitation->email)
            ->exists();

        if (! $user instanceof User && $hasExistingAccount) {
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'hasExistingAccount' => $hasExistingAccount,
        ]);
    }

    public function accept(
        Request $request,
        string $token,
        AcceptFirmInvitation $acceptFirmInvitation,
        RegisterInvitedUser $registerInvitedUser,
    ): RedirectResponse {
        $user = $request->user();

        if ($user instanceof User) {
            $membership = $acceptFirmInvitation->handle($user, $token);
        } else {
            $registered = $registerInvitedUser->handle($token, [
                'name' => (string) $request->input('name'),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
            ]);

            $user = $registered->user;
            $membership = $registered->membership;

            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->put(ResolveFirmContext::SESSION_KEY, $membership->firm_id);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Invitation accepted. Your firm workspace is ready.');
    }
}
