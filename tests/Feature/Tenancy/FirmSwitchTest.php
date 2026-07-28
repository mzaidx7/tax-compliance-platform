<?php

namespace Tests\Feature\Tenancy;

use App\Enums\FirmMembershipStatus;
use App\Http\Middleware\ResolveFirmContext;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Scopes\FirmScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmSwitchTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_user_can_switch_only_to_an_active_member_firm(): void
    {
        $user = User::factory()->create();
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $this->createFirmMembership($firmA, $user);
        $this->createFirmMembership($firmB, $user);

        $this->actingAs($user)
            ->post(route('firms.switch', $firmB))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ResolveFirmContext::SESSION_KEY, $firmB->id);

        $audit = AuditLog::withoutGlobalScope(FirmScope::class)
            ->where('action', 'firm.context.switched')
            ->sole();

        $this->assertSame($firmB->id, $audit->firm_id);
        $this->assertSame((string) $user->id, $audit->actor_id);
    }

    public function test_user_cannot_switch_to_an_unrelated_firm(): void
    {
        $user = User::factory()->create();
        $this->createFirmMembership(Firm::factory()->create(), $user);
        $unrelatedFirm = Firm::factory()->create();

        $this->actingAs($user)
            ->post(route('firms.switch', $unrelatedFirm))
            ->assertForbidden()
            ->assertSessionMissing(ResolveFirmContext::SESSION_KEY);
    }

    public function test_suspended_membership_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $firm = Firm::factory()->create();
        $this->createFirmMembership(
            $firm,
            $user,
            status: FirmMembershipStatus::Suspended,
        );

        $this->actingAs($user)
            ->post(route('firms.switch', $firm))
            ->assertForbidden();
    }
}
