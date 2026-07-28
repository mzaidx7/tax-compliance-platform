<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\ReassignWorkItem;
use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\WorkItemStatus;
use App\Livewire\Obligations\Index;
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

final class WorkItemReassignmentTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_reassigns_preparer_without_erasing_former_owner(): void
    {
        $fixture = $this->fixture();
        $replacement = $this->member($fixture['firm'], FirmRole::Preparer, 'Synthetic Replacement');

        $history = app(ReassignWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            AssignmentRole::Preparer,
            $replacement->id,
            'Synthetic workload reassignment.',
        );
        $workItem = $fixture['workItem']->refresh()->load('assignmentHistories');

        $this->assertSame($replacement->id, $history->assigned_membership_id);
        $this->assertCount(4, $workItem->assignmentHistories);
        $this->assertSame(
            $replacement->id,
            $workItem->currentAssignment(AssignmentRole::Preparer)?->assigned_membership_id,
        );
        $this->assertDatabaseHas('assignment_histories', [
            'assigned_membership_id' => $fixture['preparerMembership']->id,
            'assignment_role' => AssignmentRole::Preparer->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_item.reassigned',
            'auditable_id' => $workItem->id,
        ]);
    }

    public function test_same_owner_and_preparer_reviewer_overlap_are_rejected(): void
    {
        $fixture = $this->fixture();
        $action = app(ReassignWorkItem::class);

        try {
            $action->handle(
                $fixture['manager'],
                $fixture['workItem'],
                AssignmentRole::Preparer,
                $fixture['preparerMembership']->id,
                'Synthetic unchanged owner.',
            );
            $this->fail('Reassigning to the current owner must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replacement', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $action->handle(
            $fixture['manager'],
            $fixture['workItem'],
            AssignmentRole::Preparer,
            $fixture['reviewerMembership']->id,
            'Synthetic role overlap.',
        );
    }

    public function test_replacement_must_be_active_same_firm_with_role_permission(): void
    {
        $fixture = $this->fixture();
        $otherFirm = Firm::factory()->create();
        $otherPreparer = $this->member($otherFirm, FirmRole::Preparer);
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(ReassignWorkItem::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                AssignmentRole::Preparer,
                $otherPreparer->id,
                'Synthetic cross-firm attempt.',
            );
            $this->fail('Cross-firm reassignment must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replacement', $exception->errors());
        }

        $reviewOnly = $this->member($fixture['firm'], FirmRole::Preparer);
        $this->activateFirmMembership($fixture['managerMembership']);
        $this->expectException(ValidationException::class);
        app(ReassignWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            AssignmentRole::Reviewer,
            $reviewOnly->id,
            'Synthetic permission mismatch.',
        );
    }

    public function test_preparer_cannot_reassign_and_terminal_work_is_closed(): void
    {
        $fixture = $this->fixture();
        $replacement = $this->member($fixture['firm'], FirmRole::Preparer);
        $this->activateFirmMembership($fixture['preparerMembership']);

        try {
            app(ReassignWorkItem::class)->handle(
                $fixture['preparer'],
                $fixture['workItem'],
                AssignmentRole::Preparer,
                $replacement->id,
                'Synthetic unauthorized attempt.',
            );
            $this->fail('A preparer must not reassign work.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->activateFirmMembership($fixture['managerMembership']);
        $fixture['workItem']->update(['status' => WorkItemStatus::Cancelled]);
        $this->expectException(ValidationException::class);
        app(ReassignWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            AssignmentRole::Preparer,
            $replacement->id,
            'Synthetic terminal attempt.',
        );
    }

    public function test_manager_reassigns_through_livewire_and_former_preparer_loses_queue_visibility(): void
    {
        $fixture = $this->fixture();
        $replacementUser = User::factory()->create(['name' => 'Synthetic New Preparer']);
        $replacement = $this->createFirmMembership(
            $fixture['firm'],
            $replacementUser,
            FirmRole::Preparer,
        );
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openReassignment', $fixture['workItem']->id)
            ->assertSet('showReassignmentModal', true)
            ->assertSee('Synthetic Preparer')
            ->set('reassignmentRole', AssignmentRole::Preparer->value)
            ->set('replacementMembershipId', $replacement->id)
            ->set('reassignmentReason', 'Synthetic Livewire reassignment.')
            ->call('reassignWork')
            ->assertHasNoErrors()
            ->assertSet('showReassignmentModal', false)
            ->assertSee('Synthetic New Preparer');

        $obligationType = $fixture['workItem']->obligation->obligation_type;
        $this->activateFirmMembership($fixture['preparerMembership']);
        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertDontSee($obligationType);
        $this->activateFirmMembership($replacement);
        Livewire::actingAs($replacementUser)
            ->test(Index::class)
            ->assertSee($obligationType);
    }

    /**
     * @return array{
     * firm: Firm, manager: User, managerMembership: FirmMembership,
     * preparer: User, preparerMembership: FirmMembership,
     * reviewerMembership: FirmMembership, workItem: WorkItem
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
        app(PublishCoreWorkflowVersion::class)->handle($manager, 'Synthetic core workflow');
        app(PublishChecklistVersion::class)->handle(
            $manager,
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [['key' => 'prepare-records', 'label' => 'Prepare synthetic records']],
        );
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $this->activateFirmMembership($managerMembership);
        $obligation = Obligation::factory()->createForFirm($firm, $client, [
            'created_by' => $manager->id,
            'verified_by' => $manager->id,
        ]);
        $this->activateFirmMembership($managerMembership);
        $workItem = app(CreateAssignedWorkItem::class)->handle(
            $manager,
            $obligation,
            $preparerMembership->id,
            $reviewerMembership->id,
            $managerMembership->id,
            'Synthetic ownership.',
        );

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewerMembership',
            'workItem',
        );
    }

    private function member(Firm $firm, FirmRole $role, ?string $name = null): FirmMembership
    {
        return $this->createFirmMembership(
            $firm,
            User::factory()->create(['name' => $name ?? "Synthetic {$role->label()}"]),
            $role,
        );
    }
}
