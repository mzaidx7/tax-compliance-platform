<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Clients\CreateClient;
use App\Enums\ClientStatus;
use App\Enums\FirmRole;
use App\Livewire\Clients\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ClientManagementTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.features.client_master.enabled' => false,
            'platform.features.client_master.firm_ids' => [],
        ]);
    }

    public function test_client_register_is_not_available_while_feature_is_disabled(): void
    {
        [$administrator] = $this->administratorContext();

        $this->actingAs($administrator)
            ->get(route('clients.index'))
            ->assertNotFound();
    }

    public function test_firm_administrator_can_open_enabled_client_register(): void
    {
        $this->enableClientMaster();
        [$administrator, $firm] = $this->administratorContext();

        $this->actingAs($administrator)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee($firm->name)
            ->assertSee('Clients');
    }

    public function test_non_administrator_cannot_open_client_register(): void
    {
        $this->enableClientMaster();
        $user = User::factory()->create();
        $membership = $this->createFirmMembership(
            Firm::factory()->create(),
            $user,
            FirmRole::Manager,
        );
        $this->activateFirmMembership($membership);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_administrator_creates_a_canonical_client_with_audit_history(): void
    {
        $this->enableClientMaster();
        [$administrator, $firm] = $this->administratorContext();

        $client = app(CreateClient::class)->handle($administrator, [
            'internalCode' => ' cl-0001 ',
            'legalName' => '  Synthetic Horizon Trading LLC  ',
            'tradeName' => ' Synthetic Horizon ',
            'entityType' => ' Limited liability company ',
        ]);

        $this->assertSame($firm->id, $client->firm_id);
        $this->assertSame('CL-0001', $client->internal_code);
        $this->assertSame('CL-0001', $client->internal_code_normalized);
        $this->assertSame('Synthetic Horizon Trading LLC', $client->legal_name);
        $this->assertSame('Synthetic Horizon', $client->trade_name);
        $this->assertSame('Limited liability company', $client->entity_type);
        $this->assertSame(ClientStatus::Active, $client->status);
        $this->assertSame($administrator->id, $client->created_by);

        $audit = AuditLog::query()
            ->where('action', 'client.created')
            ->sole();

        $this->assertSame($client->id, $audit->auditable_id);
        $this->assertSame([
            'internal_code' => 'CL-0001',
            'legal_name' => 'Synthetic Horizon Trading LLC',
            'status' => ClientStatus::Active->value,
        ], $audit->after_values);
    }

    public function test_internal_code_is_case_insensitively_unique_within_firm(): void
    {
        $this->enableClientMaster();
        [$administrator] = $this->administratorContext();
        $action = app(CreateClient::class);

        $action->handle($administrator, [
            'internalCode' => 'CL-0001',
            'legalName' => 'Synthetic First Client LLC',
        ]);

        try {
            $action->handle($administrator, [
                'internalCode' => 'cl-0001',
                'legalName' => 'Synthetic Duplicate Client LLC',
            ]);
            $this->fail('A duplicate internal client code should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('internalCode', $exception->errors());
        }

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_same_internal_code_is_allowed_in_another_firm(): void
    {
        $this->enableClientMaster();
        [$administratorA] = $this->administratorContext();
        $action = app(CreateClient::class);

        $clientA = $action->handle($administratorA, [
            'internalCode' => 'CL-SHARED',
            'legalName' => 'Synthetic Alpha Client LLC',
        ]);

        $administratorB = User::factory()->create();
        $firmB = Firm::factory()->create();
        $membershipB = $this->createFirmMembership(
            $firmB,
            $administratorB,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membershipB);

        $clientB = $action->handle($administratorB, [
            'internalCode' => 'CL-SHARED',
            'legalName' => 'Synthetic Beta Client LLC',
        ]);

        $this->assertNotSame($clientA->firm_id, $clientB->firm_id);
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_livewire_register_lists_only_active_firm_clients(): void
    {
        $this->enableClientMaster();
        [$administratorA, $firmA, $membershipA] = $this->administratorContext();
        $action = app(CreateClient::class);
        $action->handle($administratorA, [
            'internalCode' => 'CL-ALPHA',
            'legalName' => 'Synthetic Visible Client LLC',
        ]);

        $administratorB = User::factory()->create();
        $firmB = Firm::factory()->create();
        $membershipB = $this->createFirmMembership(
            $firmB,
            $administratorB,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membershipB);
        $action->handle($administratorB, [
            'internalCode' => 'CL-BETA',
            'legalName' => 'Synthetic Hidden Client LLC',
        ]);

        $this->activateFirmMembership($membershipA);

        Livewire::actingAs($administratorA)
            ->test(Index::class)
            ->assertSee($firmA->name)
            ->assertSee('Synthetic Visible Client LLC')
            ->assertDontSee('Synthetic Hidden Client LLC')
            ->set('search', 'Visible')
            ->assertSee('Synthetic Visible Client LLC')
            ->assertDontSee('Synthetic Hidden Client LLC');
    }

    public function test_livewire_form_creates_client_and_clears_identity_fields(): void
    {
        $this->enableClientMaster();
        [$administrator, $firm] = $this->administratorContext();

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->set('internalCode', 'cl-live-01')
            ->set('legalName', 'Synthetic Livewire Client LLC')
            ->set('tradeName', 'Synthetic Livewire')
            ->set('entityType', 'Free zone company')
            ->call('createClient')
            ->assertHasNoErrors()
            ->assertSet('internalCode', '')
            ->assertSet('legalName', '')
            ->assertSee('Synthetic Livewire Client LLC');

        $this->assertDatabaseHas('clients', [
            'firm_id' => $firm->id,
            'internal_code' => 'CL-LIVE-01',
            'legal_name' => 'Synthetic Livewire Client LLC',
        ]);
    }

    public function test_manager_cannot_call_client_creation_action(): void
    {
        $this->enableClientMaster();
        $manager = User::factory()->create();
        $membership = $this->createFirmMembership(
            Firm::factory()->create(),
            $manager,
            FirmRole::Manager,
        );
        $this->activateFirmMembership($membership);

        $this->expectException(AuthorizationException::class);

        app(CreateClient::class)->handle($manager, [
            'internalCode' => 'CL-DENIED',
            'legalName' => 'Synthetic Denied Client LLC',
        ]);
    }

    public function test_client_records_fail_closed_without_firm_context_and_cannot_be_deleted(): void
    {
        $this->enableClientMaster();
        [$administrator] = $this->administratorContext();
        $client = app(CreateClient::class)->handle($administrator, [
            'internalCode' => 'CL-LOCKED',
            'legalName' => 'Synthetic Locked Client LLC',
        ]);

        $this->assertFalse(Gate::forUser($administrator)->allows('delete', $client));
        app(FirmContext::class)->clear();
        $this->assertSame(0, Client::query()->count());
    }

    private function enableClientMaster(): void
    {
        config([
            'platform.features.client_master.enabled' => true,
            'platform.features.client_master.firm_ids' => [],
        ]);
    }

    /**
     * @return array{User, Firm, FirmMembership}
     */
    private function administratorContext(): array
    {
        $administrator = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $administrator,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membership);

        return [$administrator, $firm, $membership];
    }
}
