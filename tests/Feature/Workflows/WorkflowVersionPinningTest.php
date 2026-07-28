<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\TransitionWorkItem;
use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\WorkItemStatus;
use App\Models\ChecklistTemplate;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use App\Models\WorkItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class WorkflowVersionPinningTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_publishes_an_immutable_audited_workflow_version(): void
    {
        $fixture = $this->fixture();
        $definition = app(PublishCoreWorkflowVersion::class)->handle(
            $fixture['manager'],
            'Synthetic controlled workflow',
        );

        $this->assertSame(1, $definition->version);
        $this->assertSame('published', $definition->status);
        $this->assertCount(22, $definition->steps);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'workflow.version_published',
            'auditable_id' => $definition->id,
        ]);

        try {
            $definition->update(['name' => 'Attempted replacement']);
            $this->fail('A published workflow definition must be immutable.');
        } catch (LogicException $exception) {
            $this->assertSame('Published workflow definitions are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $definition->steps->firstOrFail()->delete();
    }

    public function test_non_manager_cannot_publish_a_workflow_version(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(PublishCoreWorkflowVersion::class)->handle(
            $fixture['preparer'],
            'Synthetic unauthorized workflow',
        );
    }

    public function test_work_item_remains_pinned_when_a_later_version_is_published(): void
    {
        $fixture = $this->fixture();
        $versionOne = $this->publishDependencies($fixture);
        $firstWork = $this->assign($fixture, $fixture['obligation']);
        $versionTwo = app(PublishCoreWorkflowVersion::class)->handle(
            $fixture['manager'],
            'Synthetic core workflow revision',
        );
        $secondObligation = Obligation::factory()->createForFirm(
            $fixture['firm'],
            $fixture['client'],
            ['created_by' => $fixture['manager']->id, 'verified_by' => $fixture['manager']->id],
        );
        $secondWork = $this->assign($fixture, $secondObligation);

        $this->assertSame($versionOne->id, $firstWork->workflow_definition_id);
        $this->assertSame(1, $firstWork->workflowDefinition->version);
        $this->assertSame($versionTwo->id, $secondWork->workflow_definition_id);
        $this->assertSame(2, $secondWork->workflowDefinition->version);
    }

    public function test_transition_uses_the_work_items_pinned_steps(): void
    {
        $fixture = $this->fixture();
        $this->publishDependencies($fixture);
        $workItem = $this->assign($fixture, $fixture['obligation']);
        $restrictedDefinition = WorkflowDefinition::query()->create([
            'definition_key' => WorkflowDefinition::CORE_KEY,
            'name' => 'Synthetic restricted workflow',
            'version' => 2,
            'status' => 'published',
            'published_by' => $fixture['manager']->id,
            'published_at' => now('UTC'),
        ]);
        WorkflowStep::query()->create([
            'workflow_definition_id' => $restrictedDefinition->id,
            'from_status' => WorkItemStatus::NotStarted,
            'to_status' => WorkItemStatus::Cancelled,
            'assignment_role' => AssignmentRole::ResponsibleManager,
            'position' => 1,
        ]);
        $restrictedObligation = Obligation::factory()->createForFirm(
            $fixture['firm'],
            $fixture['client'],
            ['created_by' => $fixture['manager']->id, 'verified_by' => $fixture['manager']->id],
        );
        $restrictedWork = $this->assign($fixture, $restrictedObligation);
        $this->activateFirmMembership($fixture['preparerMembership']);

        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $workItem,
            WorkItemStatus::InPreparation,
            'Synthetic transition under version one.',
        );

        try {
            app(TransitionWorkItem::class)->handle(
                $fixture['preparer'],
                $restrictedWork,
                WorkItemStatus::InPreparation,
                'Synthetic transition absent from version two.',
            );
            $this->fail('A transition absent from the pinned workflow must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }
    }

    /**
     * @return array{
     * firm: Firm, manager: User, managerMembership: FirmMembership,
     * preparer: User, preparerMembership: FirmMembership,
     * reviewerMembership: FirmMembership, client: Client, obligation: Obligation
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

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewerMembership',
            'client',
            'obligation',
        );
    }

    /** @param array<string, mixed> $fixture */
    private function publishDependencies(array $fixture): WorkflowDefinition
    {
        $this->activateFirmMembership($fixture['managerMembership']);
        $definition = app(PublishCoreWorkflowVersion::class)->handle(
            $fixture['manager'],
            'Synthetic core workflow',
        );
        app(PublishChecklistVersion::class)->handle(
            $fixture['manager'],
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [['key' => 'prepare-records', 'label' => 'Prepare synthetic records']],
        );

        return $definition;
    }

    /** @param array<string, mixed> $fixture */
    private function assign(array $fixture, Obligation $obligation): WorkItem
    {
        $this->activateFirmMembership($fixture['managerMembership']);

        return app(CreateAssignedWorkItem::class)->handle(
            $fixture['manager'],
            $obligation,
            $fixture['preparerMembership']->id,
            $fixture['reviewerMembership']->id,
            $fixture['managerMembership']->id,
            'Synthetic assignment.',
        );
    }
}
