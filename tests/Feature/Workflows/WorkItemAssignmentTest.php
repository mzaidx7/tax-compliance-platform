<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class WorkItemAssignmentTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_creates_one_work_item_with_three_history_events_and_audit(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparer = $this->member($firm, FirmRole::Preparer, 'Synthetic Preparer');
        $reviewer = $this->member($firm, FirmRole::Reviewer, 'Synthetic Reviewer');
        $this->activateFirmMembership($managerMembership);

        $workItem = $this->assign($manager, $obligation, $preparer, $reviewer, $managerMembership);

        $this->assertSame($firm->id, $workItem->firm_id);
        $this->assertSame(WorkItemStatus::NotStarted, $workItem->status);
        $this->assertCount(3, $workItem->assignmentHistories);
        $this->assertEqualsCanonicalizing(
            AssignmentRole::cases(),
            $workItem->assignmentHistories->pluck('assignment_role')->all(),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_item.created_and_assigned',
            'auditable_id' => $workItem->id,
        ]);
        $this->assertSame($preparer->id, $workItem->currentAssignment(AssignmentRole::Preparer)?->assigned_membership_id);
    }

    public function test_assignment_history_rejects_updates_and_deletes(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparer = $this->member($firm, FirmRole::Preparer);
        $reviewer = $this->member($firm, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);
        $history = $this->assign($manager, $obligation, $preparer, $reviewer, $managerMembership)
            ->assignmentHistories->firstOrFail();

        try {
            $history->update(['reason' => 'Attempted overwrite']);
            $this->fail('Assignment history updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Assignment history is append-only.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $history->delete();
    }

    public function test_assignment_history_rejects_raw_query_builder_mutation(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparer = $this->member($firm, FirmRole::Preparer);
        $reviewer = $this->member($firm, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);
        $history = $this->assign($manager, $obligation, $preparer, $reviewer, $managerMembership)
            ->assignmentHistories->firstOrFail();

        try {
            DB::table('assignment_histories')
                ->where('id', $history->id)
                ->update(['reason' => 'Attempted raw overwrite']);
            $this->fail('Raw assignment history updates must fail.');
        } catch (QueryException) {
            // Expected database-level append-only enforcement.
        }

        $this->assertSame('Synthetic assignment reason.', $history->refresh()->reason);
    }

    public function test_duplicate_work_item_and_same_preparer_reviewer_are_rejected(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $reviewer = $this->member($firm, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);

        try {
            $this->assign($manager, $obligation, $reviewer, $reviewer, $managerMembership);
            $this->fail('One person cannot prepare and review the same work item.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preparer', $exception->errors());
        }

        $preparer = $this->member($firm, FirmRole::Preparer);
        $this->activateFirmMembership($managerMembership);
        $this->assign($manager, $obligation, $preparer, $reviewer, $managerMembership);

        $this->expectException(ValidationException::class);
        $this->assign($manager, $obligation, $preparer, $reviewer, $managerMembership);
    }

    public function test_cross_firm_or_underprivileged_assignees_are_rejected(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparer = $this->member($firm, FirmRole::Preparer);
        $underprivilegedReviewer = $this->member($firm, FirmRole::Preparer);
        $otherFirm = Firm::factory()->create();
        $otherManager = $this->member($otherFirm, FirmRole::Manager);
        $this->activateFirmMembership($managerMembership);

        try {
            $this->assign($manager, $obligation, $preparer, $underprivilegedReviewer, $managerMembership);
            $this->fail('A reviewer without review permission must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(AssignmentRole::Reviewer->value, $exception->errors());
        }

        $reviewer = $this->member($firm, FirmRole::Reviewer);
        $this->activateFirmMembership($managerMembership);

        try {
            $this->assign($manager, $obligation, $preparer, $reviewer, $otherManager);
            $this->fail('A cross-firm manager must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignments', $exception->errors());
        }

        $this->assertDatabaseCount('work_items', 0);
    }

    public function test_preparer_cannot_create_a_work_item(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparerUser = User::factory()->create();
        $preparer = $this->createFirmMembership($firm, $preparerUser, FirmRole::Preparer);
        $reviewer = $this->member($firm, FirmRole::Reviewer);
        $this->activateFirmMembership($preparer);

        $this->expectException(AuthorizationException::class);
        $this->assign($preparerUser, $obligation, $preparer, $reviewer, $managerMembership);
    }

    public function test_livewire_assigns_and_displays_current_owners(): void
    {
        [$manager, $firm, $managerMembership, $obligation] = $this->context();
        $preparer = $this->member($firm, FirmRole::Preparer, 'Synthetic Ada');
        $reviewer = $this->member($firm, FirmRole::Reviewer, 'Synthetic Grace');
        $this->activateFirmMembership($managerMembership);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('openAssignment', $obligation->id)
            ->assertSet('showAssignmentModal', true)
            ->set('preparerMembershipId', $preparer->id)
            ->set('reviewerMembershipId', $reviewer->id)
            ->set('managerMembershipId', $managerMembership->id)
            ->set('assignmentReason', 'Synthetic initial ownership.')
            ->call('assignWork')
            ->assertHasNoErrors()
            ->assertSet('showAssignmentModal', false)
            ->assertSee('Synthetic Ada')
            ->assertSee('Synthetic Grace');
    }

    public function test_work_state_is_separate_and_assignment_records_have_no_update_column(): void
    {
        $this->assertTrue(Schema::hasColumn('work_items', 'status'));
        $this->assertFalse(Schema::hasColumn('work_items', 'filing_status'));
        $this->assertFalse(Schema::hasColumn('work_items', 'payment_status'));
        $this->assertFalse(Schema::hasColumn('assignment_histories', 'updated_at'));
    }

    /** @return array{User, Firm, FirmMembership, Obligation} */
    private function context(): array
    {
        config([
            'platform.features.compliance_operations.enabled' => true,
            'platform.features.compliance_operations.firm_ids' => [],
        ]);
        $manager = User::factory()->create(['name' => 'Synthetic Manager']);
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $manager, FirmRole::Manager);
        $this->activateFirmMembership($membership);
        $client = Client::factory()->createForFirm($firm, ['created_by' => $manager->id]);
        $this->activateFirmMembership($membership);
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

        return [$manager, $firm, $membership, $obligation];
    }

    private function member(Firm $firm, FirmRole $role, ?string $name = null): FirmMembership
    {
        return $this->createFirmMembership($firm, User::factory()->create([
            'name' => $name ?? "Synthetic {$role->label()}",
        ]), $role);
    }

    private function assign(
        User $actor,
        Obligation $obligation,
        FirmMembership $preparer,
        FirmMembership $reviewer,
        FirmMembership $manager,
    ): WorkItem {
        return app(CreateAssignedWorkItem::class)->handle(
            $actor,
            $obligation,
            $preparer->id,
            $reviewer->id,
            $manager->id,
            'Synthetic assignment reason.',
        );
    }
}
