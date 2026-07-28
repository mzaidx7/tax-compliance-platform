<x-layouts::auth :title="__('Firm invitation')">
    <div class="flex flex-col gap-6">
        <div>
            <div class="mb-5 flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-amber-400 font-black text-black">
                    {{ mb_strtoupper(mb_substr($invitation->firm->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-zinc-100">{{ $invitation->firm->name }}</p>
                    <p class="text-xs text-zinc-400">{{ $invitation->role->label() }}</p>
                </div>
            </div>

            <flux:heading size="xl">{{ __('Review your invitation') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('This invitation was issued to :email and expires :date.', [
                    'email' => $invitation->email,
                    'date' => $invitation->expires_at->format('j M Y, H:i T'),
                ]) }}
            </flux:text>
        </div>

        @error('invitation')
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
        @enderror

        @auth
            <form method="POST" action="{{ route('invitations.accept', $token) }}" class="space-y-5">
                @csrf
                <flux:callout icon="shield-check" heading="{{ __('Secure firm access') }}">
                    {{ __('Accepting adds your existing account to this firm. It does not change access to your other firms.') }}
                </flux:callout>
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Accept invitation') }}
                </flux:button>
            </form>
        @else
            @if ($hasExistingAccount)
                <div class="space-y-5">
                    <flux:callout icon="user-circle" heading="{{ __('Your account already exists') }}">
                        {{ __('Sign in with the invited email address. You will return here to accept the invitation.') }}
                    </flux:callout>
                    <flux:button :href="route('login')" variant="primary" class="w-full">
                        {{ __('Sign in to continue') }}
                    </flux:button>
                </div>
            @else
                <form method="POST" action="{{ route('invitations.accept', $token) }}" class="space-y-5">
                    @csrf
                    <flux:input
                        name="name"
                        :label="__('Full name')"
                        :value="old('name')"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <flux:input
                        name="password"
                        :label="__('Create password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />
                    <flux:input
                        name="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Create account and accept') }}
                    </flux:button>
                </form>
            @endif
        @endauth

        <p class="text-center text-xs leading-5 text-zinc-500">
            {{ __('Only continue if you recognise the firm and expected this invitation.') }}
        </p>
    </div>
</x-layouts::auth>
