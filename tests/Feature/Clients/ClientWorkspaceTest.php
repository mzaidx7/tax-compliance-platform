<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\FirmRole;
use App\Models\Client;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientWorkspaceTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_administrator_and_manager_open_multi_tab_client_workspace(): void
    {
        $administrator = User::factory()->create();
        $manager = User::factory()->create();
        $firm = Firm::factory()->create();
        $administratorMembership = $this->createFirmMembership($firm, $administrator, FirmRole::FirmAdministrator);
        $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($administratorMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $administrator->id]);

        $this->actingAs($administrator)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee($client->legal_name)
            ->assertSee('Corporate Tax')
            ->assertSee('People')
            ->assertSee('Activity');

        $managerMembership = $firm->memberships()->where('user_id', $manager->id)->sole();
        $this->activateFirmMembership($managerMembership);
        $this->actingAs($manager)
            ->get(route('clients.show', $client))
            ->assertOk();
    }

    public function test_other_firm_cannot_open_client_workspace(): void
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $administrator, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($membership);

        $otherFirm = Firm::factory()->create();
        $otherAdministrator = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherAdministrator, FirmRole::FirmAdministrator);
        $this->activateFirmMembership($otherMembership);
        $foreignClient = Client::factory()->createForFirm($otherFirm, ['created_by' => $otherAdministrator->id]);

        $this->activateFirmMembership($membership);
        $this->actingAs($administrator)
            ->get("/clients/{$foreignClient->id}")
            ->assertNotFound();
    }
}
