<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\AcceptFirmInvitation;
use App\Actions\Tenancy\CreateFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmInvitationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_creates_invitation_with_only_a_hashed_token_stored(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();

        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            ' New.Member@Example.com ',
            FirmRole::Reviewer,
        );

        $this->assertSame('new.member@example.com', $created->invitation->email);
        $this->assertNotSame($created->plainTextToken, $created->invitation->token_hash);
        $this->assertSame(hash('sha256', $created->plainTextToken), $created->invitation->token_hash);
        $this->assertSame($firm->id, $created->invitation->firm_id);

        $audit = AuditLog::query()->where('action', 'firm.invitation.created')->sole();
        $this->assertStringNotContainsString(
            $created->plainTextToken,
            json_encode($audit->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_intended_user_can_accept_a_pending_invitation_once(): void
    {
        [$administrator] = $this->activeAdministrator();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            $invitee->email,
            FirmRole::Preparer,
        );

        $membership = app(AcceptFirmInvitation::class)->handle(
            $invitee,
            $created->plainTextToken,
        );

        $this->assertSame(FirmMembershipStatus::Active, $membership->status);
        $this->assertSame($invitee->id, $membership->user_id);
        $this->assertSame(
            FirmInvitationStatus::Accepted,
            $created->invitation->refresh()->status,
        );

        $this->expectException(ValidationException::class);
        app(AcceptFirmInvitation::class)->handle($invitee, $created->plainTextToken);
    }

    public function test_wrong_user_and_non_administrator_are_rejected(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            'intended@example.com',
            FirmRole::ReadOnly,
        );

        try {
            app(AcceptFirmInvitation::class)->handle(
                User::factory()->create(['email' => 'other@example.com']),
                $created->plainTextToken,
            );
            $this->fail('A mismatched email address accepted an invitation.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer);
        $this->activateFirmMembership($preparerMembership);

        $this->expectException(AuthorizationException::class);
        app(CreateFirmInvitation::class)->handle(
            $preparer,
            'blocked@example.com',
            FirmRole::Preparer,
        );
    }

    /**
     * @return array{User, Firm}
     */
    private function activeAdministrator(): array
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membership);

        return [$administrator, $firm];
    }
}
