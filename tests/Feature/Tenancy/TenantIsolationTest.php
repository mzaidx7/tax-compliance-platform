<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveFirmContext;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\Fixtures\Jobs\CountFirmMemberships;
use Tests\Fixtures\Livewire\MembershipLookup;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('tenancy-tests', base_path('tests/Fixtures/views'));

        Route::middleware(['web', 'auth', 'verified', 'firm.context'])
            ->get('/testing/isolated-memberships/{membership}', function (FirmMembership $membership) {
                return response()->json(['id' => $membership->id]);
            })
            ->name('testing.isolated-memberships.show');

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_http_route_binding_hides_another_firms_membership(): void
    {
        $userA = User::factory()->create();
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $this->createFirmMembership($firmA, $userA);
        $membershipB = $this->createFirmMembership($firmB, User::factory()->create());

        $this->actingAs($userA)
            ->withSession([ResolveFirmContext::SESSION_KEY => $firmA->id])
            ->get(route('testing.isolated-memberships.show', $membershipB))
            ->assertNotFound();
    }

    public function test_livewire_action_hides_another_firms_membership(): void
    {
        $userA = User::factory()->create();
        $membershipA = $this->createFirmMembership(Firm::factory()->create(), $userA);
        $membershipB = $this->createFirmMembership(
            Firm::factory()->create(),
            User::factory()->create(),
        );

        $this->activateFirmMembership($membershipA);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)
            ->test(MembershipLookup::class)
            ->call('revealMembership', $membershipB->id);
    }

    public function test_livewire_action_can_read_membership_in_the_active_firm(): void
    {
        $user = User::factory()->create();
        $membership = $this->createFirmMembership(Firm::factory()->create(), $user);

        $this->activateFirmMembership($membership);

        Livewire::actingAs($user)
            ->test(MembershipLookup::class)
            ->call('revealMembership', $membership->id)
            ->assertSet('revealedEmail', $user->email);
    }

    public function test_firm_aware_job_only_reads_its_own_firms_memberships(): void
    {
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $this->createFirmMembership($firmA, User::factory()->create());
        $this->createFirmMembership($firmB, User::factory()->create());
        $cacheKey = 'tests:firm-membership-count';

        Bus::dispatchSync(new CountFirmMemberships($firmA->id, $cacheKey));

        $this->assertSame(1, Cache::get($cacheKey));
        $this->assertFalse(app(FirmContext::class)->hasFirm());
        $this->assertSame(0, FirmMembership::query()->count());
    }
}
