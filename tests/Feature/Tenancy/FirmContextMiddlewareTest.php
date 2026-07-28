<?php

namespace Tests\Feature\Tenancy;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Http\Middleware\ResolveFirmContext;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmContextMiddlewareTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'verified', 'firm.context'])
            ->get('/testing/firm-memberships/{membership}', function (FirmMembership $membership) {
                return response()->json([
                    'id' => $membership->id,
                    'firm_id' => $membership->firm_id,
                ]);
            })
            ->name('testing.firm-memberships.show');

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_single_membership_is_selected_automatically(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        $membership = $this->createFirmMembership($firm, $user);

        $this->actingAs($user)
            ->get(route('testing.firm-memberships.show', $membership))
            ->assertOk()
            ->assertJsonPath('firm_id', $firm->id)
            ->assertSessionHas(ResolveFirmContext::SESSION_KEY, $firm->id);
    }

    public function test_user_without_active_membership_is_rejected(): void
    {
        $membership = $this->createFirmMembership(
            Firm::factory()->create(),
            User::factory()->create(),
        );

        $this->actingAs(User::factory()->create())
            ->get(route('testing.firm-memberships.show', $membership))
            ->assertForbidden();
    }

    public function test_multiple_memberships_require_an_explicit_server_side_selection(): void
    {
        $user = User::factory()->create();
        $membershipA = $this->createFirmMembership(Firm::factory()->create(), $user);
        $this->createFirmMembership(Firm::factory()->create(), $user, FirmRole::Manager);

        $this->actingAs($user)
            ->get(route('testing.firm-memberships.show', $membershipA))
            ->assertRedirect(route('firms.select'));
    }

    public function test_session_cannot_select_a_firm_the_user_does_not_belong_to(): void
    {
        $userA = User::factory()->create();
        $membershipA = $this->createFirmMembership(Firm::factory()->create(), $userA);
        $firmB = Firm::factory()->create();

        $this->actingAs($userA)
            ->withSession([ResolveFirmContext::SESSION_KEY => $firmB->id])
            ->get(route('testing.firm-memberships.show', $membershipA))
            ->assertRedirect(route('firms.select'))
            ->assertSessionMissing(ResolveFirmContext::SESSION_KEY);
    }

    public function test_submitted_firm_identifier_does_not_override_the_session_context(): void
    {
        $user = User::factory()->create();
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $membershipA = $this->createFirmMembership($firmA, $user);
        $this->createFirmMembership($firmB, $user);

        $this->actingAs($user)
            ->withSession([ResolveFirmContext::SESSION_KEY => $firmA->id])
            ->get(route('testing.firm-memberships.show', [
                'membership' => $membershipA,
                'firm_id' => $firmB->id,
            ]))
            ->assertOk()
            ->assertJsonPath('firm_id', $firmA->id);
    }

    public function test_suspended_firm_is_rejected(): void
    {
        $firm = Firm::factory()->suspended()->create();
        $user = User::factory()->create();

        $membership = new FirmMembership([
            'user_id' => $user->id,
            'role' => FirmRole::Preparer,
            'status' => FirmMembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $membership->forceFill(['firm_id' => $firm->id]);
        FirmMembership::withoutEvents(fn () => $membership->save());

        $this->actingAs($user)
            ->withSession([ResolveFirmContext::SESSION_KEY => $firm->id])
            ->get(route('testing.firm-memberships.show', $membership))
            ->assertForbidden();
    }

    public function test_suspended_membership_is_rejected(): void
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $user,
            FirmRole::Preparer,
            FirmMembershipStatus::Suspended,
        );

        $this->actingAs($user)
            ->withSession([ResolveFirmContext::SESSION_KEY => $firm->id])
            ->get(route('testing.firm-memberships.show', $membership))
            ->assertForbidden();
    }
}
