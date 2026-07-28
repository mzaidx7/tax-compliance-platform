<?php

namespace Tests\Feature\Tenancy;

use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmSelectionPageTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_multi_firm_user_is_redirected_to_a_scoped_firm_chooser(): void
    {
        $user = User::factory()->create();
        $firmA = Firm::factory()->create(['name' => 'North Ledger']);
        $firmB = Firm::factory()->create(['name' => 'South Ledger']);
        $unrelatedFirm = Firm::factory()->create(['name' => 'Unrelated Ledger']);
        $this->createFirmMembership($firmA, $user);
        $this->createFirmMembership($firmB, $user);
        $this->createFirmMembership($unrelatedFirm, User::factory()->create());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('firms.select'));

        $this->actingAs($user)
            ->get(route('firms.select'))
            ->assertOk()
            ->assertSee('North Ledger')
            ->assertSee('South Ledger')
            ->assertDontSee('Unrelated Ledger');
    }

    public function test_user_without_membership_cannot_open_firm_chooser(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('firms.select'))
            ->assertForbidden();
    }
}
