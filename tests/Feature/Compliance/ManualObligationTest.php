<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Actions\Compliance\CreateManualObligation;
use App\Enums\FirmRole;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ManualObligationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.features.compliance_operations.enabled' => false,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
    }

    public function test_obligation_register_is_not_available_while_feature_is_disabled(): void
    {
        [$manager] = $this->managerContext();

        $this->actingAs($manager)
            ->get(route('obligations.index'))
            ->assertNotFound();
    }

    public function test_manager_can_open_enabled_manual_obligation_register(): void
    {
        $this->enableComplianceOperations();
        [$manager, $firm] = $this->managerContext();

        $this->actingAs($manager)
            ->get(route('obligations.index'))
            ->assertOk()
            ->assertSee($firm->name)
            ->assertSee('Tax and compliance deadlines');
    }

    public function test_manager_creates_a_manual_obligation_with_audit_history(): void
    {
        $this->enableComplianceOperations();
        [$manager, $firm, , $client] = $this->managerContext();

        $obligation = app(CreateManualObligation::class)->handle($manager, [
            'clientId' => $client->id,
            'obligationType' => ' Manual VAT review ',
            'periodLabel' => ' Synthetic Q2 2026 ',
            'statutoryDueDate' => '2026-09-28',
            'internalTargetDate' => '2026-09-21',
            'sourceReference' => ' Reviewed against a synthetic internal schedule. ',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);

        $this->assertSame($firm->id, $obligation->firm_id);
        $this->assertSame($client->id, $obligation->client_id);
        $this->assertSame('Manual VAT review', $obligation->obligation_type);
        $this->assertSame('Synthetic Q2 2026', $obligation->period_label);
        $this->assertSame('2026-09-28', $obligation->statutory_due_date->toDateString());
        $this->assertSame('2026-09-28', $obligation->effectiveDueDate()->toDateString());
        $this->assertSame('2026-09-21', $obligation->internal_target_date?->toDateString());
        $this->assertSame(ObligationOrigin::Manual, $obligation->origin);
        $this->assertSame(ObligationStatus::Open, $obligation->status);
        $this->assertSame('Reviewed against a synthetic internal schedule.', $obligation->source_reference);
        $this->assertSame($manager->id, $obligation->verified_by);
        $this->assertSame($manager->id, $obligation->created_by);

        $audit = AuditLog::query()
            ->where('action', 'obligation.manual_created')
            ->sole();

        $this->assertSame($obligation->id, $audit->auditable_id);
        $this->assertSame($client->id, $audit->after_values['client_id']);
        $this->assertSame(ObligationOrigin::Manual->value, $audit->after_values['origin']);
        $this->assertSame(ObligationStatus::Open->value, $audit->after_values['status']);
        $this->assertArrayNotHasKey('source_reference', $audit->after_values);
    }

    public function test_internal_target_cannot_be_after_statutory_due_date(): void
    {
        $this->enableComplianceOperations();
        [$manager, , , $client] = $this->managerContext();

        try {
            app(CreateManualObligation::class)->handle($manager, [
                'clientId' => $client->id,
                'obligationType' => 'Synthetic manual review',
                'statutoryDueDate' => '2026-09-28',
                'internalTargetDate' => '2026-09-29',
                'sourceReference' => 'Synthetic validation fixture.',
                'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
            ]);
            $this->fail('An internal target after the statutory date should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('internalTargetDate', $exception->errors());
        }

        $this->assertDatabaseCount('obligations', 0);
    }

    public function test_future_verification_date_is_rejected(): void
    {
        $this->enableComplianceOperations();
        [$manager, , , $client] = $this->managerContext();

        try {
            app(CreateManualObligation::class)->handle($manager, [
                'clientId' => $client->id,
                'obligationType' => 'Synthetic manual review',
                'statutoryDueDate' => '2026-09-28',
                'sourceReference' => 'Synthetic validation fixture.',
                'lastVerifiedOn' => now('Asia/Dubai')->addDay()->toDateString(),
            ]);
            $this->fail('A future verification date should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lastVerifiedOn', $exception->errors());
        }
    }

    public function test_manager_cannot_attach_another_firms_client(): void
    {
        $this->enableComplianceOperations();
        [$manager] = $this->managerContext();
        $otherFirm = Firm::factory()->create();
        $otherClient = Client::factory()->createForFirm($otherFirm, [
            'created_by' => User::factory()->create()->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(CreateManualObligation::class)->handle($manager, [
            'clientId' => $otherClient->id,
            'obligationType' => 'Synthetic cross-firm review',
            'statutoryDueDate' => '2026-09-28',
            'sourceReference' => 'Synthetic isolation fixture.',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);
    }

    public function test_preparer_can_open_work_register_but_cannot_create_manual_obligations(): void
    {
        $this->enableComplianceOperations();
        $preparer = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $preparer->id]);
        $this->activateFirmMembership($membership);

        Livewire::actingAs($preparer)
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee('Record manual obligation');

        $this->expectException(AuthorizationException::class);
        app(CreateManualObligation::class)->handle($preparer, [
            'clientId' => $client->id,
            'obligationType' => 'Synthetic denied review',
            'statutoryDueDate' => '2026-09-28',
            'sourceReference' => 'Synthetic authorization fixture.',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);
    }

    public function test_livewire_register_lists_only_active_firm_obligations(): void
    {
        $this->enableComplianceOperations();
        [$managerA, $firmA, $membershipA, $clientA] = $this->managerContext();
        $action = app(CreateManualObligation::class);
        $action->handle($managerA, [
            'clientId' => $clientA->id,
            'obligationType' => 'Synthetic visible VAT review',
            'statutoryDueDate' => '2026-09-28',
            'sourceReference' => 'Synthetic visible fixture.',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);

        [$managerB, , , $clientB] = $this->managerContext();
        $action->handle($managerB, [
            'clientId' => $clientB->id,
            'obligationType' => 'Synthetic hidden licence review',
            'statutoryDueDate' => '2026-10-15',
            'sourceReference' => 'Synthetic hidden fixture.',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);

        $this->activateFirmMembership($membershipA);

        Livewire::actingAs($managerA)
            ->test(Index::class)
            ->assertSee($firmA->name)
            ->assertSee('Synthetic visible VAT review')
            ->assertDontSee('Synthetic hidden licence review');
    }

    public function test_livewire_form_creates_obligation_and_clears_entry_fields(): void
    {
        $this->enableComplianceOperations();
        [$manager, $firm, , $client] = $this->managerContext();

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->set('clientId', $client->id)
            ->set('obligationType', 'Synthetic Livewire review')
            ->set('periodLabel', 'Synthetic FY 2026')
            ->set('statutoryDueDate', '2026-12-31')
            ->set('internalTargetDate', '2026-12-20')
            ->set('sourceReference', 'Synthetic Livewire verification note.')
            ->call('createObligation')
            ->assertHasNoErrors()
            ->assertSet('clientId', '')
            ->assertSet('obligationType', '')
            ->assertSee('Synthetic Livewire review');

        $this->assertDatabaseHas('obligations', [
            'firm_id' => $firm->id,
            'client_id' => $client->id,
            'obligation_type' => 'Synthetic Livewire review',
            'origin' => ObligationOrigin::Manual->value,
            'status' => ObligationStatus::Open->value,
        ]);
    }

    public function test_obligation_state_stays_separate_from_future_work_filing_and_payment_states(): void
    {
        $this->assertTrue(Schema::hasColumn('obligations', 'status'));
        $this->assertFalse(Schema::hasColumn('obligations', 'work_status'));
        $this->assertFalse(Schema::hasColumn('obligations', 'filing_status'));
        $this->assertFalse(Schema::hasColumn('obligations', 'payment_status'));
    }

    public function test_obligations_fail_closed_without_context_and_have_no_deletion_path(): void
    {
        $this->enableComplianceOperations();
        [$manager, , , $client] = $this->managerContext();
        $obligation = app(CreateManualObligation::class)->handle($manager, [
            'clientId' => $client->id,
            'obligationType' => 'Synthetic retained review',
            'statutoryDueDate' => '2026-09-28',
            'sourceReference' => 'Synthetic retention fixture.',
            'lastVerifiedOn' => now('Asia/Dubai')->toDateString(),
        ]);

        $this->assertFalse(Gate::forUser($manager)->allows('delete', $obligation));
        app(FirmContext::class)->clear();
        $this->assertSame(0, Obligation::query()->count());
    }

    private function enableComplianceOperations(): void
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
    }

    /**
     * @return array{User, Firm, FirmMembership, Client}
     */
    private function managerContext(): array
    {
        $manager = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, [
            'created_by' => $manager->id,
        ]);
        $this->activateFirmMembership($membership);

        return [$manager, $firm, $membership, $client];
    }
}
