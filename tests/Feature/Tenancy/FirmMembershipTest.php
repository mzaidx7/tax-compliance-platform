<?php

namespace Tests\Feature\Tenancy;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmMembershipTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_membership_keeps_role_and_access_state_on_the_firm_relationship(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $user,
            FirmRole::FirmAdministrator,
        );

        $this->activateFirmMembership($membership);

        $this->assertSame($firm->id, $membership->firm->id);
        $this->assertSame($user->id, $membership->user->id);
        $this->assertSame(FirmRole::FirmAdministrator, $membership->role);
        $this->assertSame(FirmMembershipStatus::Active, $membership->status);
        $this->assertNotNull($membership->joined_at);
        $this->assertTrue($firm->memberships()->whereKey($membership)->exists());
        $this->assertTrue($user->firmMemberships()->whereKey($membership)->exists());
        $this->assertSame($user->id, $firm->users()->sole()->id);
        $this->assertSame($firm->id, $user->firms()->sole()->id);
    }

    public function test_membership_factory_persists_through_a_trusted_firm_context(): void
    {
        $firm = Firm::factory()->create();

        $membership = FirmMembership::factory()->createForFirm($firm);

        $this->assertSame($firm->id, $membership->firm_id);
        $this->assertSame(FirmMembershipStatus::Active, $membership->status);
    }

    public function test_membership_creation_requires_a_trusted_firm_context(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();

        $membership = new FirmMembership([
            'user_id' => $user->id,
            'role' => FirmRole::Preparer,
            'status' => FirmMembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $membership->forceFill(['firm_id' => $firm->id]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tenant-owned records require an active firm context.');

        $membership->save();
    }

    public function test_membership_cannot_move_to_another_firm(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firmA,
            User::factory()->create(),
        );

        $this->activateFirmMembership($membership);
        $membership->forceFill(['firm_id' => $firmB->id]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A tenant-owned record cannot be assigned to another firm.');

        $membership->save();
    }

    public function test_database_rejects_duplicate_membership_for_one_user_and_firm(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();

        $this->createFirmMembership($firm, $user);

        $this->expectException(QueryException::class);

        $this->createFirmMembership($firm, $user, FirmRole::Manager);
    }

    public function test_membership_queries_fail_closed_without_firm_context(): void
    {
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            User::factory()->create(),
        );

        $this->assertSame(0, FirmMembership::query()->count());
        $this->assertSame(
            1,
            FirmMembership::withoutGlobalScope(FirmScope::class)->count(),
        );

        $this->activateFirmMembership($membership);

        $this->assertSame(1, FirmMembership::query()->count());
    }
}
