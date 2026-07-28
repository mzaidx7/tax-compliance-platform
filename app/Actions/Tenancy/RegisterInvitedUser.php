<?php

namespace App\Actions\Tenancy;

use App\Concerns\PasswordValidationRules;
use App\Data\RegisteredInvitationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterInvitedUser
{
    use PasswordValidationRules;

    public function __construct(
        private FindPendingFirmInvitation $findPendingFirmInvitation,
        private AcceptFirmInvitation $acceptFirmInvitation,
    ) {}

    /**
     * @param  array{name: string, password: string, password_confirmation: string}  $input
     */
    public function handle(string $plainTextToken, array $input): RegisteredInvitationUser
    {
        return DB::transaction(function () use ($plainTextToken, $input): RegisteredInvitationUser {
            $invitation = $this->findPendingFirmInvitation->handle($plainTextToken);

            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists for this address. Sign in to accept the invitation.',
                ]);
            }

            $validated = Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'password' => $this->passwordRules(),
            ])->validate();

            $user = new User([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
            ]);
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            $membership = $this->acceptFirmInvitation->handle($user, $plainTextToken);

            return new RegisteredInvitationUser($user, $membership);
        }, 3);
    }
}
