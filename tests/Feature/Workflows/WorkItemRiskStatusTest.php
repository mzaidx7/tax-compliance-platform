<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Payments\CreatePaymentRecord;
use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\SetWorkItemRiskStatus;
use App\Enums\FirmRole;
use App\Enums\PaymentStatus;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Livewire\Obligations\Index;
use App\Models\AuditLog;
use App\Models\ChecklistTemplate;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class WorkItemRiskStatusTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_work_item_opens_unassessed(): void
    {
        $fixture = $this->fixture();

        $this->assertSame(RiskLevel::Unassessed, $fixture['workItem']->refresh()->risk_status);
    }

    public function test_manager_sets_risk_with_history_and_audit(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        $change = app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk assessment.',
        );

        $this->assertSame(RiskLevel::Unassessed, $change->previous_risk_status);
        $this->assertSame(RiskLevel::High, $change->new_risk_status);
        $this->assertSame(RiskLevel::High, $fixture['workItem']->refresh()->risk_status);

        $audit = AuditLog::query()->where('action', 'work_item.risk_status_changed')->sole();
        $this->assertSame(RiskLevel::Unassessed->value, $audit->before_values['risk_status']);
        $this->assertSame(RiskLevel::High->value, $audit->after_values['risk_status']);
    }

    public function test_setting_the_same_risk_level_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(SetWorkItemRiskStatus::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                RiskLevel::Unassessed,
                'Synthetic no-op attempt.',
            );
            $this->fail('Setting the same risk level must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('riskLevel', $exception->errors());
        }
    }

    public function test_blank_reason_is_rejected_without_writing_history(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(SetWorkItemRiskStatus::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                RiskLevel::Medium,
                ' ',
            );
            $this->fail('A blank risk reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertDatabaseCount('work_item_risk_changes', 0);
    }

    public function test_risk_status_does_not_change_work_or_payment_state(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        app(CreatePaymentRecord::class)->handle(
            $fixture['manager'],
            $fixture['obligation'],
            PaymentStatus::Pending,
            'Synthetic payment opened.',
        );

        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk assessment.',
        );

        $this->assertSame(WorkItemStatus::NotStarted, $fixture['workItem']->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $fixture['obligation']->paymentRecord->refresh()->status);
    }

    public function test_member_without_assign_work_permission_cannot_set_risk(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(SetWorkItemRiskStatus::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic unauthorised attempt.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_set_this_firms_risk(): void
    {
        $fixture = $this->fixture();

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(SetWorkItemRiskStatus::class)->handle(
            $otherManager,
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_risk_history_rejects_model_and_raw_query_builder_mutations(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        $change = app(SetWorkItemRiskStatus::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            RiskLevel::High,
            'Synthetic risk assessment.',
        );

        try {
            $change->update(['reason' => 'Attempted overwrite']);
            $this->fail('Risk history updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Work item risk history is append-only.', $exception->getMessage());
        }

        try {
            DB::table('work_item_risk_changes')->where('id', $change->id)->update(['reason' => 'Bulk overwrite']);
            $this->fail('A database trigger must reject a raw risk history update.');
        } catch (QueryException) {
            // Expected.
        }

        try {
            DB::table('work_item_risk_changes')->where('id', $change->id)->delete();
            $this->fail('A database trigger must reject a raw risk history delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame('Synthetic risk assessment.', $change->refresh()->reason);
    }

    public function test_manager_sets_risk_through_the_livewire_interface(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openRisk', $fixture['workItem']->id)
            ->assertSet('showRiskModal', true)
            ->set('riskLevel', RiskLevel::High->value)
            ->set('riskReason', 'Synthetic Livewire risk assessment.')
            ->call('saveRisk')
            ->assertHasNoErrors()
            ->assertSet('showRiskModal', false)
            ->assertSee('Risk: High');
    }

    public function test_preparer_does_not_see_the_risk_control(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertDontSeeHtml("wire:click=\"openRisk('{$fixture['workItem']->id}')\"");
    }

    /**
     * @return array{
     *   firm: Firm,
     *   manager: User,
     *   managerMembership: FirmMembership,
     *   preparer: User,
     *   preparerMembership: FirmMembership,
     *   obligation: Obligation,
     *   workItem: WorkItem
     * }
     */
    private function fixture(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $firm = Firm::factory()->create();
        $manager = User::factory()->create(['name' => 'Synthetic Manager']);
        $preparer = User::factory()->create(['name' => 'Synthetic Preparer']);
        $reviewer = User::factory()->create(['name' => 'Synthetic Reviewer']);
        $managerMembership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $preparerMembership = $this->createFirmMembership($firm, $preparer, FirmRole::Preparer);
        $reviewerMembership = $this->createFirmMembership($firm, $reviewer, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        app(PublishCoreWorkflowVersion::class)->handle($manager, 'Synthetic core workflow');
        app(PublishChecklistVersion::class)->handle(
            $manager,
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [['key' => 'prepare-records', 'label' => 'Prepare synthetic records']],
        );
        $workItem = app(CreateAssignedWorkItem::class)->handle(
            $manager,
            $obligation,
            $preparerMembership->id,
            $reviewerMembership->id,
            $managerMembership->id,
            'Synthetic initial ownership.',
        );
        $this->activateFirmMembership($managerMembership);

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'obligation',
            'workItem',
        );
    }
}
