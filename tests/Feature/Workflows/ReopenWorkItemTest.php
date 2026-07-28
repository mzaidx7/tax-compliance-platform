<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CompleteChecklistItem;
use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\DecideWorkItemReview;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\ReopenWorkItem;
use App\Actions\Workflows\TransitionWorkItem;
use App\Enums\AssignmentRole;
use App\Enums\FirmRole;
use App\Enums\ReviewDecision;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ReopenWorkItemTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_reopens_completed_work_as_a_linked_follow_up(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        $followUp = app(ReopenWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            'Synthetic correction required.',
        );

        $this->assertSame($fixture['workItem']->id, $followUp->parent_work_item_id);
        $this->assertNull($followUp->primary_obligation_id);
        $this->assertSame($fixture['workItem']->obligation_id, $followUp->obligation_id);
        $this->assertSame(WorkItemStatus::NotStarted, $followUp->status);
        $this->assertSame(RiskLevel::Unassessed, $followUp->risk_status);
        $this->assertNotNull($followUp->checklist);

        $audit = AuditLog::query()->where('action', 'work_item.reopened')->sole();
        $this->assertSame($fixture['workItem']->id, $audit->after_values['parent_work_item_id']);
    }

    public function test_the_completed_original_is_left_entirely_unchanged(): void
    {
        $fixture = $this->completedFixture();
        $original = $fixture['workItem'];
        $transitionsBefore = $original->transitions()->count();
        $assignmentsBefore = $original->assignmentHistories()->count();
        $completionsBefore = $original->checklist->completions()->count();

        $this->activateFirmMembership($fixture['managerMembership']);
        app(ReopenWorkItem::class)->handle($fixture['manager'], $original, 'Synthetic correction required.');

        $refreshed = $original->refresh();
        $this->assertSame(WorkItemStatus::Completed, $refreshed->status);
        $this->assertNull($refreshed->parent_work_item_id);
        $this->assertSame($refreshed->obligation_id, $refreshed->primary_obligation_id);
        $this->assertSame($transitionsBefore, $refreshed->transitions()->count());
        $this->assertSame($assignmentsBefore, $refreshed->assignmentHistories()->count());
        $this->assertSame($completionsBefore, $refreshed->checklist->completions()->count());
    }

    public function test_the_follow_up_carries_the_originals_current_owners(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        $followUp = app(ReopenWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            'Synthetic correction required.',
        );

        $this->assertSame(
            $fixture['preparerMembership']->id,
            $followUp->currentAssignment(AssignmentRole::Preparer)?->assigned_membership_id,
        );
        $this->assertSame(
            $fixture['reviewerMembership']->id,
            $followUp->currentAssignment(AssignmentRole::Reviewer)?->assigned_membership_id,
        );
        $this->assertSame(
            $fixture['managerMembership']->id,
            $followUp->currentAssignment(AssignmentRole::ResponsibleManager)?->assigned_membership_id,
        );
    }

    public function test_incomplete_work_cannot_be_reopened(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(ReopenWorkItem::class)->handle(
                $fixture['manager'],
                $fixture['workItem'],
                'Synthetic premature reopen.',
            );
            $this->fail('Only completed work may be reopened.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reopen', $exception->errors());
        }

        $this->assertDatabaseCount('work_items', 1);
    }

    public function test_a_second_open_follow_up_is_rejected(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        app(ReopenWorkItem::class)->handle($fixture['manager'], $fixture['workItem'], 'Synthetic first reopen.');

        try {
            app(ReopenWorkItem::class)->handle($fixture['manager'], $fixture['workItem'], 'Synthetic second reopen.');
            $this->fail('A second open follow-up must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reopen', $exception->errors());
        }

        $this->assertSame(1, $fixture['workItem']->followUps()->count());
    }

    public function test_a_follow_up_cannot_itself_be_reopened(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        $followUp = app(ReopenWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            'Synthetic first reopen.',
        );
        $followUp->forceFill(['status' => WorkItemStatus::Completed])->save();

        try {
            app(ReopenWorkItem::class)->handle($fixture['manager'], $followUp, 'Synthetic nested reopen.');
            $this->fail('A follow-up must not be reopened.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reopen', $exception->errors());
        }
    }

    public function test_reopen_migration_refuses_destructive_rollback_when_follow_up_work_exists(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);
        $followUp = app(ReopenWorkItem::class)->handle(
            $fixture['manager'],
            $fixture['workItem'],
            'Synthetic rollback guard.',
        );

        $migration = require database_path(
            'migrations/2026_07_28_010000_add_follow_up_links_to_work_items_table.php',
        );

        try {
            $migration->down();
            $this->fail('Rollback must not destroy linked follow-up work.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('forward recovery migration', $exception->getMessage());
        }

        $this->assertDatabaseHas('work_items', [
            'id' => $followUp->id,
            'parent_work_item_id' => $fixture['workItem']->id,
        ]);
    }

    public function test_blank_reason_is_rejected_without_creating_work(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        try {
            app(ReopenWorkItem::class)->handle($fixture['manager'], $fixture['workItem'], ' ');
            $this->fail('A blank reopen reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertDatabaseCount('work_items', 1);
    }

    public function test_member_without_assign_work_permission_cannot_reopen(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(ReopenWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            'Synthetic unauthorised reopen.',
        );
    }

    public function test_a_manager_from_another_firm_cannot_reopen_this_firms_work(): void
    {
        $fixture = $this->completedFixture();

        $otherFirm = Firm::factory()->create();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(ReopenWorkItem::class)->handle(
            $otherManager,
            $fixture['workItem'],
            'Synthetic cross-firm reopen.',
        );
    }

    public function test_manager_reopens_through_the_livewire_interface(): void
    {
        $fixture = $this->completedFixture();
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->call('openReopen', $fixture['workItem']->id)
            ->assertSet('showReopenModal', true)
            ->set('reopenReason', 'Synthetic Livewire reopen.')
            ->call('reopenWork')
            ->assertHasNoErrors()
            ->assertSet('showReopenModal', false)
            ->assertSee('linked follow-up');

        $this->assertSame(1, $fixture['workItem']->followUps()->count());
    }

    /**
     * @return array{
     *   firm: Firm, manager: User, managerMembership: FirmMembership,
     *   preparer: User, preparerMembership: FirmMembership,
     *   reviewer: User, reviewerMembership: FirmMembership,
     *   obligation: Obligation, workItem: WorkItem
     * }
     */
    private function completedFixture(): array
    {
        $fixture = $this->fixture();
        $workItem = $fixture['workItem'];

        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $workItem,
            WorkItemStatus::InPreparation,
            'Synthetic preparation start.',
        );
        app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $workItem,
            $workItem->load('checklist.version.items')->checklist->version->items->firstOrFail(),
            'Synthetic checklist evidence.',
        );
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $workItem,
            WorkItemStatus::UnderReview,
            'Synthetic submission for review.',
        );

        $this->activateFirmMembership($fixture['reviewerMembership']);
        app(DecideWorkItemReview::class)->handle(
            $fixture['reviewer'],
            $workItem,
            ReviewDecision::Approve,
            'Synthetic approval.',
        );
        app(TransitionWorkItem::class)->handle(
            $fixture['reviewer'],
            $workItem,
            WorkItemStatus::ReadyToFile,
            'Synthetic ready to file.',
        );

        $this->activateFirmMembership($fixture['managerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['manager'],
            $workItem,
            WorkItemStatus::Completed,
            'Synthetic completion.',
        );

        return $fixture;
    }

    /**
     * @return array{
     *   firm: Firm, manager: User, managerMembership: FirmMembership,
     *   preparer: User, preparerMembership: FirmMembership,
     *   reviewer: User, reviewerMembership: FirmMembership,
     *   obligation: Obligation, workItem: WorkItem
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

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewer',
            'reviewerMembership',
            'obligation',
            'workItem',
        );
    }
}
