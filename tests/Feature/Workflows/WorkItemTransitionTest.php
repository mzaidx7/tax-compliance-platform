<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CompleteChecklistItem;
use App\Actions\Workflows\CreateAssignedWorkItem;
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
use App\Models\WorkItemTransition;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class WorkItemTransitionTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_assigned_preparer_transitions_work_with_immutable_evidence_and_audit(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        $transition = $this->transition(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
        );

        $this->assertSame(WorkItemStatus::NotStarted, $transition->from_status);
        $this->assertSame(WorkItemStatus::InPreparation, $transition->to_status);
        $this->assertSame($fixture['preparer']->id, $transition->transitioned_by);
        $this->assertSame(WorkItemStatus::InPreparation, $fixture['workItem']->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_item.status_transitioned',
            'auditable_id' => $fixture['workItem']->id,
        ]);

        $audit = AuditLog::query()->where('action', 'work_item.status_transitioned')->sole();
        $this->assertSame(WorkItemStatus::NotStarted->value, $audit->before_values['status']);
        $this->assertSame(WorkItemStatus::InPreparation->value, $audit->after_values['status']);
    }

    public function test_unassigned_member_cannot_transition_work(): void
    {
        $fixture = $this->fixture();
        $otherPreparer = User::factory()->create();
        $otherMembership = $this->createFirmMembership($fixture['firm'], $otherPreparer, FirmRole::Preparer);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(ValidationException::class);
        $this->transition($otherPreparer, $fixture['workItem'], WorkItemStatus::InPreparation);
    }

    public function test_preparer_submits_for_review_and_only_assigned_reviewer_can_decide(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $this->transition($fixture['preparer'], $fixture['workItem'], WorkItemStatus::InPreparation);
        app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $fixture['workItem']->checklist->version->items->firstOrFail(),
            'Synthetic checklist evidence before review.',
        );
        $this->transition($fixture['preparer'], $fixture['workItem'], WorkItemStatus::UnderReview);

        try {
            $this->transition($fixture['preparer'], $fixture['workItem'], WorkItemStatus::ReturnedForChanges);
            $this->fail('A preparer must not decide review work.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }

        $this->activateFirmMembership($fixture['reviewerMembership']);
        $transition = $this->transition(
            $fixture['reviewer'],
            $fixture['workItem'],
            WorkItemStatus::ReturnedForChanges,
        );

        $this->assertSame(WorkItemStatus::ReturnedForChanges, $transition->to_status);
    }

    public function test_invalid_state_skip_and_blank_reason_are_rejected_without_history(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        try {
            $this->transition($fixture['preparer'], $fixture['workItem'], WorkItemStatus::ReadyToFile);
            $this->fail('An invalid state skip must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }

        try {
            app(TransitionWorkItem::class)->handle(
                $fixture['preparer'],
                $fixture['workItem'],
                WorkItemStatus::InPreparation,
                ' ',
            );
            $this->fail('A blank transition reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertDatabaseCount('work_item_transitions', 0);
    }

    public function test_responsible_manager_can_cancel_but_an_unassigned_manager_cannot(): void
    {
        $fixture = $this->fixture();
        $otherManager = User::factory()->create();
        $otherMembership = $this->createFirmMembership($fixture['firm'], $otherManager, FirmRole::Manager);
        $this->activateFirmMembership($otherMembership);

        try {
            $this->transition($otherManager, $fixture['workItem'], WorkItemStatus::Cancelled);
            $this->fail('Only the assigned responsible manager can cancel work.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('targetStatus', $exception->errors());
        }

        $this->activateFirmMembership($fixture['managerMembership']);
        $this->transition($fixture['manager'], $fixture['workItem'], WorkItemStatus::Cancelled);
        $this->assertSame(WorkItemStatus::Cancelled, $fixture['workItem']->refresh()->status);
    }

    public function test_completed_and_cancelled_states_are_terminal(): void
    {
        $this->assertSame([], WorkItemStatus::Completed->allowedTransitions());
        $this->assertSame([], WorkItemStatus::Cancelled->allowedTransitions());
    }

    public function test_transition_history_rejects_updates_and_deletes(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $transition = $this->transition(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
        );

        try {
            $transition->update(['reason' => 'Attempted overwrite']);
            $this->fail('Transition history updates must fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Work item transition history is append-only.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $transition->delete();
    }

    public function test_transition_history_rejects_raw_query_builder_mutations(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $transition = $this->transition(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
        );

        try {
            DB::table('work_item_transitions')
                ->where('id', $transition->id)
                ->update(['reason' => 'Attempted bulk overwrite']);
            $this->fail('A database trigger must reject a raw query-builder update, since Eloquent model events never fire for mass operations.');
        } catch (QueryException) {
            // Expected: the database-level trigger rejects the mutation independently of Eloquent.
        }

        try {
            DB::table('work_item_transitions')->where('id', $transition->id)->delete();
            $this->fail('A database trigger must reject a raw query-builder delete.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertSame('Synthetic work status reason.', $transition->refresh()->reason);
    }

    public function test_assigned_preparer_uses_livewire_transition_interface(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->assertSee('Synthetic Preparer')
            ->assertDontSee('Record manual obligation')
            ->call('openTransition', $fixture['workItem']->id)
            ->assertSet('showTransitionModal', true)
            ->assertSee('In preparation')
            ->set('targetWorkItemStatus', WorkItemStatus::InPreparation->value)
            ->set('transitionReason', 'Synthetic Livewire transition.')
            ->call('transitionWork')
            ->assertHasNoErrors()
            ->assertSet('showTransitionModal', false)
            ->assertSee('Task: In preparation');

        $this->assertDatabaseHas('work_item_transitions', [
            'work_item_id' => $fixture['workItem']->id,
            'to_status' => WorkItemStatus::InPreparation->value,
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
        );

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

    private function transition(
        User $actor,
        WorkItem $workItem,
        WorkItemStatus $targetStatus,
    ): WorkItemTransition {
        return app(TransitionWorkItem::class)->handle(
            $actor,
            $workItem,
            $targetStatus,
            'Synthetic work status reason.',
        );
    }
}
