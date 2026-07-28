<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateFirmInvitation;
use App\Actions\Tenancy\FindPendingFirmInvitation;
use App\Actions\Tenancy\RevokeFirmInvitation;
use App\Actions\Tenancy\RotateFirmInvitation;
use App\Enums\FirmInvitationStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmInvitationLifecycleTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_resend_rotates_token_and_expiry_and_invalidates_old_link(): void
    {
        [$administrator] = $this->activeAdministrator();
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            'rotated@example.com',
            FirmRole::Reviewer,
        );
        $oldHash = $created->invitation->token_hash;
        $oldExpiry = $created->invitation->expires_at;

        $rotated = app(RotateFirmInvitation::class)->handle(
            $administrator,
            $created->invitation,
        );

        $this->assertNotSame($oldHash, $rotated->invitation->token_hash);
        $this->assertSame(
            hash('sha256', $rotated->plainTextToken),
            $rotated->invitation->token_hash,
        );
        $this->assertTrue($rotated->invitation->expires_at->greaterThanOrEqualTo($oldExpiry));

        try {
            app(FindPendingFirmInvitation::class)->handle($created->plainTextToken);
            $this->fail('The replaced invitation token remained valid.');
        } catch (ValidationException) {
            $this->assertSame(
                $rotated->invitation->id,
                app(FindPendingFirmInvitation::class)
                    ->handle($rotated->plainTextToken)
                    ->id,
            );
        }

        $audit = AuditLog::query()->where('action', 'firm.invitation.resent')->sole();
        $this->assertStringNotContainsString(
            $rotated->plainTextToken,
            json_encode($audit->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_administrator_can_revoke_pending_invitation_with_reason(): void
    {
        [$administrator] = $this->activeAdministrator();
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            'revoked@example.com',
            FirmRole::Preparer,
        );

        $revoked = app(RevokeFirmInvitation::class)->handle(
            $administrator,
            $created->invitation,
            'Recipient no longer requires access.',
        );

        $this->assertSame(FirmInvitationStatus::Revoked, $revoked->status);
        $this->assertNotNull($revoked->revoked_at);

        $audit = AuditLog::query()->where('action', 'firm.invitation.revoked')->sole();
        $this->assertSame('Recipient no longer requires access.', $audit->reason);

        $this->expectException(ValidationException::class);
        app(FindPendingFirmInvitation::class)->handle($created->plainTextToken);
    }

    public function test_non_administrator_and_cross_tenant_invitation_are_rejected(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $created = app(CreateFirmInvitation::class)->handle(
            $administrator,
            'protected@example.com',
            FirmRole::ReadOnly,
        );

        $preparer = User::factory()->create();
        $preparerMembership = $this->createFirmMembership($firm, $preparer);
        $this->activateFirmMembership($preparerMembership);

        try {
            app(RotateFirmInvitation::class)->handle($preparer, $created->invitation);
            $this->fail('A preparer rotated an invitation.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        [$otherAdministrator] = $this->activeAdministrator();

        $this->expectException(ModelNotFoundException::class);
        app(RevokeFirmInvitation::class)->handle(
            $otherAdministrator,
            $created->invitation,
            'Cross-tenant attempt.',
        );
    }

    public function test_existing_inactive_membership_cannot_receive_a_new_invitation(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $existingUser = User::factory()->create(['email' => 'inactive@example.com']);
        $this->createFirmMembership(
            $firm,
            $existingUser,
            FirmRole::Preparer,
            FirmMembershipStatus::Suspended,
        );

        $this->expectException(ValidationException::class);
        app(CreateFirmInvitation::class)->handle(
            $administrator,
            $existingUser->email,
            FirmRole::Reviewer,
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
