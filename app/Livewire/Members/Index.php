<?php

namespace App\Livewire\Members;

use App\Actions\Tenancy\CreateFirmInvitation;
use App\Actions\Tenancy\RevokeFirmInvitation;
use App\Actions\Tenancy\RotateFirmInvitation;
use App\Actions\Tenancy\UpdateFirmMembershipRole;
use App\Actions\Tenancy\UpdateFirmMembershipStatus;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\FirmInvitation;
use App\Models\FirmMembership;
use App\Models\User;
use App\Notifications\FirmInvitationNotification;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Team members')]
class Index extends Component
{
    public string $email = '';

    public string $role = FirmRole::Preparer->value;

    public bool $showMemberModal = false;

    #[Locked]
    public string $selectedMembershipId = '';

    #[Locked]
    public string $selectedMemberName = '';

    #[Locked]
    public string $selectedMemberStatus = '';

    public string $selectedRole = '';

    public string $memberReason = '';

    public bool $showInvitationModal = false;

    #[Locked]
    public string $selectedInvitationId = '';

    #[Locked]
    public string $selectedInvitationEmail = '';

    public string $invitationReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', FirmMembership::class);
    }

    public function invite(CreateFirmInvitation $createFirmInvitation): void
    {
        Gate::authorize('create', FirmMembership::class);

        $validated = $this->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(FirmRole::class)],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $created = $createFirmInvitation->handle(
            $user,
            $validated['email'],
            FirmRole::from($validated['role']),
        );

        Notification::route('mail', $created->invitation->email)
            ->notify(new FirmInvitationNotification(
                plainTextToken: $created->plainTextToken,
                firmName: $created->invitation->firm->name,
                expiresAt: $created->invitation->expires_at->format('j M Y, H:i T'),
                inviterName: $user->name,
            ));

        $this->reset('email');
        $this->role = FirmRole::Preparer->value;
        unset($this->invitations);

        Flux::toast(
            variant: 'success',
            text: 'Invitation queued for delivery.',
        );
    }

    public function openMemberManagement(string $membershipId): void
    {
        $membership = FirmMembership::query()
            ->with('user')
            ->findOrFail($membershipId);

        Gate::authorize('update', $membership);

        if ($membership->user_id === $this->currentUser()->getKey()) {
            $this->addError('member', 'You cannot change your own firm access.');

            return;
        }

        if ($membership->status === FirmMembershipStatus::Revoked) {
            $this->addError('member', 'A revoked membership is retained as a read-only access record.');

            return;
        }

        $this->resetErrorBag();
        $this->selectedMembershipId = $membership->id;
        $this->selectedMemberName = $membership->user->name;
        $this->selectedMemberStatus = $membership->status->value;
        $this->selectedRole = $membership->role->value;
        $this->memberReason = '';
        $this->showMemberModal = true;
    }

    public function updateMemberRole(UpdateFirmMembershipRole $updateFirmMembershipRole): void
    {
        $validated = $this->validate([
            'selectedRole' => ['required', Rule::enum(FirmRole::class)],
            'memberReason' => ['required', 'string', 'max:500'],
        ]);

        $membership = FirmMembership::query()->findOrFail($this->selectedMembershipId);
        $updateFirmMembershipRole->handle(
            $this->currentUser(),
            $membership,
            FirmRole::from($validated['selectedRole']),
            $validated['memberReason'],
        );

        $this->completeMemberChange('Member role updated.');
    }

    public function suspendMember(UpdateFirmMembershipStatus $updateFirmMembershipStatus): void
    {
        $this->changeMemberStatus(
            $updateFirmMembershipStatus,
            FirmMembershipStatus::Suspended,
            'Member access suspended.',
        );
    }

    public function reactivateMember(UpdateFirmMembershipStatus $updateFirmMembershipStatus): void
    {
        $this->changeMemberStatus(
            $updateFirmMembershipStatus,
            FirmMembershipStatus::Active,
            'Member access reactivated.',
        );
    }

    public function revokeMember(UpdateFirmMembershipStatus $updateFirmMembershipStatus): void
    {
        $this->changeMemberStatus(
            $updateFirmMembershipStatus,
            FirmMembershipStatus::Revoked,
            'Member access revoked.',
        );
    }

    public function closeMemberModal(): void
    {
        $this->reset(
            'showMemberModal',
            'selectedMembershipId',
            'selectedMemberName',
            'selectedMemberStatus',
            'selectedRole',
            'memberReason',
        );
        $this->resetErrorBag();
    }

    public function resendInvitation(
        string $invitationId,
        RotateFirmInvitation $rotateFirmInvitation,
    ): void {
        $invitation = FirmInvitation::query()->findOrFail($invitationId);
        $rotated = $rotateFirmInvitation->handle($this->currentUser(), $invitation);

        Notification::route('mail', $rotated->invitation->email)
            ->notify(new FirmInvitationNotification(
                plainTextToken: $rotated->plainTextToken,
                firmName: $rotated->invitation->firm->name,
                expiresAt: $rotated->invitation->expires_at->format('j M Y, H:i T'),
                inviterName: $this->currentUser()->name,
            ));

        unset($this->invitations);
        Flux::toast(variant: 'success', text: 'A replacement invitation link was queued.');
    }

    public function openInvitationRevocation(string $invitationId): void
    {
        $invitation = FirmInvitation::query()->findOrFail($invitationId);

        if ($invitation->status !== FirmInvitationStatus::Pending) {
            $this->addError('invitation', 'Only a pending invitation can be revoked.');

            return;
        }

        $this->resetErrorBag();
        $this->selectedInvitationId = $invitation->id;
        $this->selectedInvitationEmail = $invitation->email;
        $this->invitationReason = '';
        $this->showInvitationModal = true;
    }

    public function revokeInvitation(RevokeFirmInvitation $revokeFirmInvitation): void
    {
        $validated = $this->validate([
            'invitationReason' => ['required', 'string', 'max:500'],
        ]);

        $invitation = FirmInvitation::query()->findOrFail($this->selectedInvitationId);
        $revokeFirmInvitation->handle(
            $this->currentUser(),
            $invitation,
            $validated['invitationReason'],
        );

        $this->closeInvitationModal();
        unset($this->invitations);
        Flux::toast(variant: 'success', text: 'Invitation revoked.');
    }

    public function closeInvitationModal(): void
    {
        $this->reset(
            'showInvitationModal',
            'selectedInvitationId',
            'selectedInvitationEmail',
            'invitationReason',
        );
        $this->resetErrorBag();
    }

    /**
     * @return Collection<int, FirmMembership>
     */
    #[Computed]
    public function members(): Collection
    {
        return FirmMembership::query()
            ->with('user')
            ->orderByRaw(
                "case status when 'active' then 0 when 'suspended' then 1 when 'revoked' then 2 else 3 end",
            )
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, FirmInvitation>
     */
    #[Computed]
    public function invitations(): Collection
    {
        return FirmInvitation::query()
            ->where('status', FirmInvitationStatus::Pending)
            ->latest()
            ->get();
    }

    /**
     * @return list<FirmRole>
     */
    #[Computed]
    public function roles(): array
    {
        return FirmRole::cases();
    }

    #[Computed]
    public function currentFirmName(): string
    {
        return app(FirmContext::class)->firm()->name;
    }

    #[Computed]
    public function currentRoleLabel(): string
    {
        return app(FirmContext::class)->membership()?->role->label() ?? '';
    }

    #[Computed]
    public function currentUserId(): int
    {
        return $this->currentUser()->id;
    }

    private function changeMemberStatus(
        UpdateFirmMembershipStatus $updateFirmMembershipStatus,
        FirmMembershipStatus $newStatus,
        string $successMessage,
    ): void {
        $validated = $this->validate([
            'memberReason' => ['required', 'string', 'max:500'],
        ]);

        $membership = FirmMembership::query()->findOrFail($this->selectedMembershipId);
        $updateFirmMembershipStatus->handle(
            $this->currentUser(),
            $membership,
            $newStatus,
            $validated['memberReason'],
        );

        $this->completeMemberChange($successMessage);
    }

    private function completeMemberChange(string $message): void
    {
        $this->closeMemberModal();
        unset($this->members);
        Flux::toast(variant: 'success', text: $message);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.members.index');
    }
}
