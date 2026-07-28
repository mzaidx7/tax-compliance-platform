<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-7">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-2 text-sm font-medium text-amber-300">{{ $this->currentFirmName }}</p>
                <h1 class="text-3xl font-semibold tracking-[-0.03em] text-zinc-50 sm:text-4xl">
                    {{ __('Team members') }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">
                    {{ __('Invite team members, assign roles, update access and review invitation status. Every change is recorded in the activity history.') }}
                </p>
            </div>
            <flux:badge color="amber" icon="shield-check">{{ $this->currentRoleLabel }}</flux:badge>
        </div>
    </header>

    <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="member-list-heading">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @php
                        $activeMemberCount = $this->members
                            ->where('status', \App\Enums\FirmMembershipStatus::Active)
                            ->count();
                    @endphp
                    <h2 id="member-list-heading" class="text-lg font-semibold text-zinc-100">{{ __('Firm members') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ trans_choice(':count active member|:count active members', $activeMemberCount, ['count' => $activeMemberCount]) }}
                    </p>
                </div>
            </div>

            <flux:error name="member" class="mb-4" />

            <div class="overflow-hidden rounded-2xl bg-zinc-900/70 ring-1 ring-white/8">
                <div class="divide-y divide-white/8">
                    @forelse ($this->members as $membership)
                        @php
                            $statusColor = match ($membership->status) {
                                \App\Enums\FirmMembershipStatus::Active => 'green',
                                \App\Enums\FirmMembershipStatus::Suspended => 'amber',
                                \App\Enums\FirmMembershipStatus::Revoked => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <article
                            wire:key="membership-{{ $membership->id }}"
                            class="grid gap-4 px-5 py-5 sm:grid-cols-[minmax(0,1fr)_11rem_7rem_auto] sm:items-center sm:px-6"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <flux:avatar
                                    :name="$membership->user->name"
                                    :initials="$membership->user->initials()"
                                    class="shrink-0"
                                />
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-zinc-100">{{ $membership->user->name }}</p>
                                        @if ($membership->user_id === $this->currentUserId)
                                            <flux:badge size="sm">{{ __('You') }}</flux:badge>
                                        @endif
                                    </div>
                                    <p class="truncate text-sm text-zinc-500">{{ $membership->user->email }}</p>
                                </div>
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 sm:hidden">{{ __('Role') }}</span>
                                <span class="text-sm text-zinc-300">{{ $membership->role->label() }}</span>
                            </div>
                            <div class="sm:text-right">
                                <flux:badge :color="$statusColor">
                                    {{ $membership->status->label() }}
                                </flux:badge>
                            </div>
                            <div class="sm:text-right">
                                @if (
                                    $membership->user_id !== $this->currentUserId
                                    && $membership->status !== \App\Enums\FirmMembershipStatus::Revoked
                                )
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        icon="ellipsis-horizontal"
                                        wire:click="openMemberManagement('{{ $membership->id }}')"
                                        :aria-label="__('Manage access for :name', ['name' => $membership->user->name])"
                                    >
                                        {{ __('Manage') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-600">
                                        {{ $membership->status === \App\Enums\FirmMembershipStatus::Revoked ? __('Closed record') : __('Current session') }}
                                    </span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <p class="text-sm font-medium text-zinc-200">{{ __('No firm members found') }}</p>
                            <p class="mt-2 text-sm text-zinc-500">{{ __('Invite a member to begin building the firm team.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($this->invitations->isNotEmpty())
                <div class="mt-9">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Pending invitations') }}</h2>
                    <div class="mt-4 divide-y divide-white/8 border-y border-white/8">
                        @foreach ($this->invitations as $invitation)
                            <div
                                wire:key="invitation-{{ $invitation->id }}"
                                class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-200">{{ $invitation->email }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ $invitation->role->label() }} · {{ __('Expires :date', ['date' => $invitation->expires_at->format('j M Y, H:i')]) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge :color="$invitation->expires_at->isPast() ? 'red' : 'amber'">
                                        {{ $invitation->expires_at->isPast() ? __('Expired') : $invitation->status->label() }}
                                    </flux:badge>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        wire:click="resendInvitation('{{ $invitation->id }}')"
                                        wire:confirm="{{ __('Send a replacement link? The previous invitation link will stop working.') }}"
                                    >
                                        {{ __('Resend') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                        wire:click="openInvitationRevocation('{{ $invitation->id }}')"
                                    >
                                        {{ __('Revoke') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <aside aria-labelledby="invite-heading" class="xl:sticky xl:top-8 xl:self-start">
            <div class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <div class="mb-6">
                    <span class="mb-4 grid size-10 place-items-center rounded-xl bg-amber-400 text-black">
                        <flux:icon.user-plus class="size-5" />
                    </span>
                    <h2 id="invite-heading" class="text-lg font-semibold text-zinc-100">{{ __('Invite a member') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        {{ __('We will send a secure link that expires after 72 hours.') }}
                    </p>
                </div>

                <form wire:submit="invite" class="space-y-5">
                    <flux:input
                        wire:model="email"
                        type="email"
                        :label="__('Email address')"
                        placeholder="person@example.com"
                        autocomplete="email"
                        required
                    />

                    <flux:select wire:model="role" :label="__('Firm role')" required>
                        @foreach ($this->roles as $roleOption)
                            <flux:select.option :value="$roleOption->value">
                                {{ $roleOption->label() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        wire:loading.attr="disabled"
                        wire:target="invite"
                    >
                        <span wire:loading.remove wire:target="invite">{{ __('Send invitation') }}</span>
                        <span wire:loading wire:target="invite">{{ __('Sending invitation...') }}</span>
                    </flux:button>
                </form>
            </div>
        </aside>
    </div>

    <flux:modal
        name="manage-member-access"
        wire:model="showMemberModal"
        @close="closeMemberModal"
        class="max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Manage member access') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Update :name within :firm. A reason is required and will be retained in the audit history.', [
                        'name' => $selectedMemberName,
                        'firm' => $this->currentFirmName,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="member" />

            <form wire:submit="updateMemberRole" class="space-y-5">
                <flux:select wire:model="selectedRole" :label="__('Firm role')" required>
                    @foreach ($this->roles as $roleOption)
                        <flux:select.option :value="$roleOption->value">
                            {{ $roleOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model="memberReason"
                    :label="__('Reason for change')"
                    :description="__('Use a concise operational reason. Maximum 500 characters.')"
                    rows="3"
                    maxlength="500"
                    required
                />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <flux:button type="button" variant="ghost" wire:click="closeMemberModal">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        {{ __('Save role') }}
                    </flux:button>
                </div>
            </form>

            <div class="border-t border-white/8 pt-5">
                <p class="text-sm font-medium text-zinc-200">{{ __('Access status') }}</p>
                <p class="mt-1 text-sm leading-6 text-zinc-500">
                    {{ __('Suspension is reversible. Revocation closes this membership record and cannot be reversed in this packet.') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    @if ($selectedMemberStatus === \App\Enums\FirmMembershipStatus::Active->value)
                        <flux:button
                            type="button"
                            variant="filled"
                            wire:click="suspendMember"
                            wire:confirm="{{ __('Suspend this member immediately?') }}"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Suspend access') }}
                        </flux:button>
                    @elseif ($selectedMemberStatus === \App\Enums\FirmMembershipStatus::Suspended->value)
                        <flux:button
                            type="button"
                            variant="filled"
                            wire:click="reactivateMember"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Reactivate access') }}
                        </flux:button>
                    @endif

                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="revokeMember"
                        wire:confirm="{{ __('Revoke this membership permanently? This action cannot be reversed in the current platform.') }}"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Revoke access') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="revoke-invitation"
        wire:model="showInvitationModal"
        @close="closeInvitationModal"
        class="max-w-md"
    >
        <form wire:submit="revokeInvitation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Revoke invitation') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('The link sent to :email will stop working immediately.', ['email' => $selectedInvitationEmail]) }}
                </flux:text>
            </div>

            <flux:error name="invitation" />

            <flux:textarea
                wire:model="invitationReason"
                :label="__('Reason for revocation')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="ghost" wire:click="closeInvitationModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                    {{ __('Revoke invitation') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
