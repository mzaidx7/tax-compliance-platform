<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\MigrateWorkItemWorkflowVersion;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\TransitionWorkItem;
use App\Enums\FirmRole;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class MigrateWorkItemWorkflowVersionTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_migrates_open_work_to_a_later_published_version_with_append_only_audit(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        $this->activateFirmMembership($fixture['managerMembership']);

        $migrated = app(MigrateWorkItemWorkflowVersion::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            $laterVersion->id,
            'Synthetic explicit migration.',
        );

        $this->assertSame($laterVersion->id, $migrated->workflow_definition_id);
        $this->assertSame(WorkItemStatus::NotStarted, $migrated->status);

        $audit = AuditLog::query()->where('action', 'work_item.workflow_version_migrated')->sole();
        $this->assertSame(1, $audit->before_values['workflow_version']);
        $this->assertSame(2, $audit->after_values['workflow_version']);
        $this->assertSame($laterVersion->id, $audit->after_values['workflow_definition_id']);
    }

    public function test_migration_preserves_existing_transition_assignment_and_checklist_history(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
            'Synthetic preparation start before migration.',
        );
        $transitionCountBefore = $fixture['workItem']->transitions()->count();
        $assignmentCountBefore = $fixture['workItem']->assignmentHistories()->count();
        $checklistId = $fixture['workItem']->checklist->id;

        $this->activateFirmMembership($fixture['managerMembership']);
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        app(MigrateWorkItemWorkflowVersion::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            $laterVersion->id,
            'Synthetic migration preserving history.',
        );

        $refreshed = $fixture['workItem']->refresh();
        $this->assertSame($transitionCountBefore, $refreshed->transitions()->count());
        $this->assertSame($assignmentCountBefore, $refreshed->assignmentHistories()->count());
        $this->assertSame($checklistId, $refreshed->checklist->id);
        $this->assertSame(WorkItemStatus::InPreparation, $refreshed->status);
    }

    public function test_migration_is_rejected_for_completed_work(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        $this->activateFirmMembership($fixture['managerMembership']);
        $fixture['workItem']->update(['status' => WorkItemStatus::Completed]);

        try {
            app(MigrateWorkItemWorkflowVersion::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                $laterVersion->id,
                'Synthetic attempt on completed work.',
            );
            $this->fail('Completed work must not be migrated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetDefinitionId', $exception->errors());
        }
    }

    public function test_migration_is_rejected_for_cancelled_work(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        $this->activateFirmMembership($fixture['managerMembership']);
        $fixture['workItem']->update(['status' => WorkItemStatus::Cancelled]);

        try {
            app(MigrateWorkItemWorkflowVersion::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                $laterVersion->id,
                'Synthetic attempt on cancelled work.',
            );
            $this->fail('Cancelled work must not be migrated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetDefinitionId', $exception->errors());
        }
    }

    public function test_migration_is_rejected_to_an_earlier_or_equal_version(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(MigrateWorkItemWorkflowVersion::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                $fixture['workItem']->workflow_definition_id,
                'Synthetic attempt on the same pinned version.',
            );
            $this->fail('Migration to the currently pinned version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetDefinitionId', $exception->errors());
        }
    }

    public function test_member_without_assign_work_permission_cannot_migrate(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(MigrateWorkItemWorkflowVersion::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $laterVersion->id,
            'Synthetic unauthorised attempt.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_migrate_this_firms_work_item(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(MigrateWorkItemWorkflowVersion::class)->handle(
            $otherManager,
            $fixture['workItem'],
            $laterVersion->id,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_manager_migrates_through_livewire_interface(): void
    {
        $fixture = $this->fixture();
        $laterVersion = app(PublishCoreWorkflowVersion::class)->handle($fixture['manager'], 'Synthetic later workflow');
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openMigration', $fixture['workItem']->id)
            ->assertSet('showMigrationModal', true)
            ->set('migrationTargetDefinitionId', $laterVersion->id)
            ->set('migrationReason', 'Synthetic Livewire migration.')
            ->call('migrateWorkflowVersion')
            ->assertHasNoErrors()
            ->assertSet('showMigrationModal', false);

        $this->assertSame($laterVersion->id, $fixture['workItem']->refresh()->workflow_definition_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_item.workflow_version_migrated',
            'auditable_id' => $fixture['workItem']->id,
        ]);
    }

    /**
     * @return array{
     *   firm: Firm,
     *   manager: User,
     *   managerMembership: FirmMembership,
     *   preparer: User,
     *   preparerMembership: FirmMembership,
     *   reviewer: User,
     *   reviewerMembership: FirmMembership,
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
        $this->activateFirmMembership($managerMembership);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        $this->activateFirmMembership($managerMembership);
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
        )->load('checklist.version.items', 'workflowDefinition');

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewer',
            'reviewerMembership',
            'workItem',
        );
    }
}
