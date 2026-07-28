<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateFirmInvitation;
use App\Data\CreatedFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmRole;
use App\Http\Middleware\ResolveFirmContext;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class InvitationAcceptancePageTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_new_invited_user_can_create_account_and_enter_firm(): void
    {
        [$created, $firm] = $this->createInvitation('new.member@example.com');

        $this->get(route('invitations.show', $created->plainTextToken))
            ->assertOk()
            ->assertSee('Create account and accept')
            ->assertSee($firm->name);

        $this->post(route('invitations.accept', $created->plainTextToken), [
            'name' => 'New Member',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ResolveFirmContext::SESSION_KEY, $firm->id);

        $user = User::query()->where('email', 'new.member@example.com')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('firm_users', [
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'role' => FirmRole::Reviewer->value,
        ]);
        $this->assertSame(FirmInvitationStatus::Accepted, $created->invitation->refresh()->status);
    }

    public function test_existing_user_is_directed_to_sign_in_then_can_accept(): void
    {
        $invitee = User::factory()->create(['email' => 'existing@example.com']);
        [$created, $firm] = $this->createInvitation($invitee->email);

        $this->get(route('invitations.show', $created->plainTextToken))
            ->assertOk()
            ->assertSee('Sign in to continue')
            ->assertSessionHas('url.intended', route('invitations.show', $created->plainTextToken));

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $created->plainTextToken))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ResolveFirmContext::SESSION_KEY, $firm->id);

        $membership = FirmMembership::withoutGlobalScope(FirmScope::class)
            ->where('firm_id', $firm->id)
            ->where('user_id', $invitee->id)
            ->sole();

        $this->assertSame(FirmRole::Reviewer, $membership->role);
    }

    public function test_signed_in_user_cannot_view_another_email_invitation(): void
    {
        [$created] = $this->createInvitation('intended@example.com');

        $this->actingAs(User::factory()->create(['email' => 'wrong@example.com']))
            ->get(route('invitations.show', $created->plainTextToken))
            ->assertForbidden();
    }

    /**
     * @return array{CreatedFirmInvitation, Firm}
     */
    private function createInvitation(string $email): array
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create(['name' => 'Synthetic Advisory']);
        $membership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membership);

        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            $email,
            FirmRole::Reviewer,
        );

        return [$created, $firm];
    }
}
