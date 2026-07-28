<?php

namespace Tests\Feature\Livewire\Members;

use App\Actions\Tenancy\CreateFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Livewire\Members\Index;
use App\Models\Firm;
use App\Models\FirmInvitation;
use App\Models\User;
use App\Notifications\FirmInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_sees_only_members_of_the_active_firm(): void
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create(['name' => 'Current Firm']);
        $administratorMembership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $visibleUser = User::factory()->create(['name' => 'Visible Member']);
        $this->createFirmMembership($firm, $visibleUser);
        $hiddenUser = User::factory()->create(['name' => 'Hidden Member']);
        $this->createFirmMembership(Firm::factory()->create(), $hiddenUser);
        $this->activateFirmMembership($administratorMembership);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Current Firm')
            ->assertSee('Visible Member')
            ->assertDontSee('Hidden Member');
    }

    public function test_administrator_can_queue_a_hashed_invitation(): void
    {
        Notification::fake();

        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membership);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('email', 'invitee@example.com')
            ->set('role', FirmRole::Reviewer->value)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('email', '');

        $invitation = $this->assertDatabaseHas('firm_invitations', [
            'firm_id' => $firm->id,
            'email' => 'invitee@example.com',
            'role' => FirmRole::Reviewer->value,
        ]);

        Notification::assertSentOnDemand(
            FirmInvitationNotification::class,
            function (FirmInvitationNotification $notification, array $channels): bool {
                return $channels === ['mail']
                    && hash('sha256', $notification->plainTextToken) ===
                        FirmInvitation::query()->sole()->token_hash;
            },
        );
    }

    public function test_non_administrator_cannot_open_member_management(): void
    {
        $user = User::factory()->create();
        $membership = $this->createFirmMembership(
            Firm::factory()->create(),
            $user,
            FirmRole::Preparer,
        );
        $this->activateFirmMembership($membership);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_administrator_can_update_role_and_suspend_member_from_register(): void
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $administratorMembership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $target = $this->createFirmMembership(
            $firm,
            User::factory()->create(['name' => 'Lifecycle Member']),
            FirmRole::Preparer,
        );
        $this->activateFirmMembership($administratorMembership);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('openMemberManagement', $target->id)
            ->assertSet('showMemberModal', true)
            ->set('selectedRole', FirmRole::Reviewer->value)
            ->set('memberReason', 'Review responsibilities assigned.')
            ->call('updateMemberRole')
            ->assertHasNoErrors()
            ->assertSet('showMemberModal', false);

        $this->assertSame(FirmRole::Reviewer, $target->refresh()->role);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('openMemberManagement', $target->id)
            ->set('memberReason', 'Temporary access pause.')
            ->call('suspendMember')
            ->assertHasNoErrors();

        $this->assertSame(FirmMembershipStatus::Suspended, $target->refresh()->status);
    }

    public function test_administrator_can_rotate_and_revoke_invitation_from_register(): void
    {
        Notification::fake();

        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $administratorMembership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($administratorMembership);
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            'lifecycle@example.com',
            FirmRole::ReadOnly,
        );
        $oldTokenHash = $created->invitation->token_hash;

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('resendInvitation', $created->invitation->id)
            ->assertHasNoErrors();

        $rotatedInvitation = $created->invitation->refresh();
        $this->assertNotSame($oldTokenHash, $rotatedInvitation->token_hash);
        Notification::assertSentOnDemand(FirmInvitationNotification::class);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('openInvitationRevocation', $rotatedInvitation->id)
            ->assertSet('showInvitationModal', true)
            ->set('invitationReason', 'Recipient access request was withdrawn.')
            ->call('revokeInvitation')
            ->assertHasNoErrors()
            ->assertSet('showInvitationModal', false);

        $this->assertSame(
            FirmInvitationStatus::Revoked,
            $rotatedInvitation->refresh()->status,
        );
    }
}
