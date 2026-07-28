<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ReadinessComingSoonTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_authenticated_firm_member_sees_the_release_boundary(): void
    {
        [$user, $firm] = $this->member();

        $this->actingAs($user)
            ->withSession(['active_firm_id' => $firm->id])
            ->get(route('readiness.coming-soon'))
            ->assertOk()
            ->assertSee('E-invoicing readiness is coming soon.')
            ->assertSee('Return to compliance dashboard')
            ->assertDontSee('Create rule version');
    }

    public function test_unreleased_readiness_workspaces_are_not_routable(): void
    {
        [$user, $firm] = $this->member();

        foreach (['readiness/rules', 'readiness/parties', 'readiness/invoices'] as $path) {
            $this->actingAs($user)
                ->withSession(['active_firm_id' => $firm->id])
                ->get($path)
                ->assertNotFound();
        }
    }

    /** @return array{User, Firm} */
    private function member(): array
    {
        $firm = Firm::factory()->create();
        $user = User::factory()->create();
        $this->createFirmMembership($firm, $user, FirmRole::FirmAdministrator);

        return [$user, $firm];
    }
}
