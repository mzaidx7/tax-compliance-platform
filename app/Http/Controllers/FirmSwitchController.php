<?php

namespace App\Http\Controllers;

use App\Actions\Tenancy\SwitchActiveFirm;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FirmSwitchController extends Controller
{
    public function __invoke(
        Request $request,
        string $firm,
        SwitchActiveFirm $switchActiveFirm,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $switchActiveFirm->handle($user, $firm, $request->session());

        return redirect()->route('dashboard');
    }
}
