<?php

namespace Tests\Feature\Authorization;

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmMembershipPolicyTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_firm_administrator_can_manage_members_in_the_active_firm_only(): void
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $administratorMembership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $target = $this->createFirmMembership($firm, User::factory()->create());
        $otherTarget = $this->createFirmMembership(
            Firm::factory()->create(),
            User::factory()->create(),
        );

        $this->activateFirmMembership($administratorMembership);

        $this->assertTrue(Gate::forUser($administrator)->allows('viewAny', FirmMembership::class));
        $this->assertTrue(Gate::forUser($administrator)->allows('update', $target));
        $this->assertTrue(Gate::forUser($administrator)->allows('delete', $target));
        $this->assertFalse(Gate::forUser($administrator)->allows('update', $otherTarget));
    }

    public function test_non_administrator_can_view_self_but_cannot_manage_members(): void
    {
        $user = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $user, FirmRole::Preparer);
        $target = $this->createFirmMembership($firm, User::factory()->create());

        $this->activateFirmMembership($membership);

        $this->assertTrue(Gate::forUser($user)->allows('view', $membership));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', FirmMembership::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $target));
    }
}
