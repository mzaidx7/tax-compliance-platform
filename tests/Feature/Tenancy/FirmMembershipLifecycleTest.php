<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\UpdateFirmMembershipRole;
use App\Actions\Tenancy\UpdateFirmMembershipStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmMembershipLifecycleTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_can_change_another_members_role_with_audit_reason(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $target = $this->createFirmMembership($firm, User::factory()->create(), FirmRole::Preparer);

        $updated = app(UpdateFirmMembershipRole::class)->handle(
            $administrator,
            $target,
            FirmRole::Reviewer,
            'Moving review ownership to this member.',
        );

        $this->assertSame(FirmRole::Reviewer, $updated->role);

        $audit = AuditLog::query()
            ->where('action', 'firm.membership.role_changed')
            ->sole();

        $this->assertSame(['role' => FirmRole::Preparer->value], $audit->before_values);
        $this->assertSame(['role' => FirmRole::Reviewer->value], $audit->after_values);
        $this->assertSame('Moving review ownership to this member.', $audit->reason);
    }

    public function test_administrator_can_suspend_reactivate_and_revoke_a_member(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $target = $this->createFirmMembership($firm, User::factory()->create());
        $action = app(UpdateFirmMembershipStatus::class);

        $suspended = $action->handle(
            $administrator,
            $target,
            FirmMembershipStatus::Suspended,
            'Temporary leave.',
        );
        $this->assertSame(FirmMembershipStatus::Suspended, $suspended->status);
        $this->assertNotNull($suspended->suspended_at);

        $reactivated = $action->handle(
            $administrator,
            $suspended,
            FirmMembershipStatus::Active,
            'Returned to active duties.',
        );
        $this->assertSame(FirmMembershipStatus::Active, $reactivated->status);
        $this->assertNull($reactivated->suspended_at);

        $revoked = $action->handle(
            $administrator,
            $reactivated,
            FirmMembershipStatus::Revoked,
            'Access is no longer required.',
        );
        $this->assertSame(FirmMembershipStatus::Revoked, $revoked->status);
        $this->assertNotNull($revoked->revoked_at);

        $this->assertSame(
            [
                'firm.membership.suspended',
                'firm.membership.reactivated',
                'firm.membership.revoked',
            ],
            AuditLog::query()->orderBy('created_at')->pluck('action')->all(),
        );
    }

    public function test_administrator_cannot_change_own_role_or_status(): void
    {
        [$administrator] = $this->activeAdministrator();
        $membership = app(FirmContext::class)->membership();
        $this->assertNotNull($membership);

        try {
            app(UpdateFirmMembershipRole::class)->handle(
                $administrator,
                $membership,
                FirmRole::Manager,
                'Invalid self change.',
            );
            $this->fail('An administrator changed their own role.');
        } catch (ValidationException) {
            $this->assertSame(FirmRole::FirmAdministrator, $membership->refresh()->role);
        }

        $this->expectException(ValidationException::class);
        app(UpdateFirmMembershipStatus::class)->handle(
            $administrator,
            $membership,
            FirmMembershipStatus::Suspended,
            'Invalid self change.',
        );
    }

    public function test_revoked_membership_is_terminal_and_reason_is_required(): void
    {
        [$administrator, $firm] = $this->activeAdministrator();
        $target = $this->createFirmMembership($firm, User::factory()->create());
        $action = app(UpdateFirmMembershipStatus::class);
        $revoked = $action->handle(
            $administrator,
            $target,
            FirmMembershipStatus::Revoked,
            'Engagement ended.',
        );

        try {
            $action->handle(
                $administrator,
                $revoked,
                FirmMembershipStatus::Active,
                'Attempting recovery.',
            );
            $this->fail('A revoked membership was reactivated.');
        } catch (ValidationException) {
            $this->assertSame(FirmMembershipStatus::Revoked, $revoked->refresh()->status);
        }

        $targetTwo = $this->createFirmMembership($firm, User::factory()->create());
        $this->expectException(ValidationException::class);
        $action->handle(
            $administrator,
            $targetTwo,
            FirmMembershipStatus::Suspended,
            ' ',
        );
    }

    public function test_cross_tenant_membership_cannot_be_changed(): void
    {
        [$administrator] = $this->activeAdministrator();
        $otherFirm = Firm::factory()->create();
        $otherMembership = $this->createFirmMembership(
            $otherFirm,
            User::factory()->create(),
        );

        $this->expectException(ModelNotFoundException::class);
        app(UpdateFirmMembershipRole::class)->handle(
            $administrator,
            $otherMembership,
            FirmRole::Reviewer,
            'Cross-tenant attempt.',
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
