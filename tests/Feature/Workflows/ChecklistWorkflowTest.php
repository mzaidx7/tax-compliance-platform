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
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\Client;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

final class ChecklistWorkflowTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_manager_publishes_immutable_ordered_checklist_version(): void
    {
        $fixture = $this->fixture();
        $version = $fixture['version'];

        $this->assertSame('published', $version->status);
        $this->assertSame(1, $version->version);
        $this->assertSame(['source-review', 'preparation-note'], $version->items->pluck('item_key')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'checklist.version_published',
            'auditable_id' => $version->id,
        ]);

        $this->expectException(LogicException::class);
        $version->update(['version' => 2]);
    }

    public function test_new_work_item_snapshots_latest_published_core_version(): void
    {
        $fixture = $this->fixture();

        $this->assertSame($fixture['version']->id, $fixture['workItem']->checklist?->checklist_version_id);
        $this->assertSame($fixture['manager']->id, $fixture['workItem']->checklist?->applied_by);
    }

    public function test_assigned_preparer_completes_item_with_append_only_evidence_and_audit(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $item = $fixture['version']->items->firstOrFail();

        $completion = app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $item,
            'Synthetic records reviewed and preparation note retained.',
        );

        $this->assertSame($item->id, $completion->checklist_item_id);
        $this->assertSame($fixture['preparer']->id, $completion->completed_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'checklist.item_completed',
            'auditable_id' => $fixture['workItem']->id,
        ]);

        $this->expectException(LogicException::class);
        $completion->delete();
    }

    public function test_checklist_completion_rejects_raw_query_builder_mutation(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $completion = app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $fixture['version']->items->firstOrFail(),
            'Synthetic retained evidence.',
        );

        try {
            DB::table('checklist_item_completions')
                ->where('id', $completion->id)
                ->delete();
            $this->fail('Raw checklist evidence deletion must fail.');
        } catch (QueryException) {
            // Expected database-level append-only enforcement.
        }

        $this->assertDatabaseHas('checklist_item_completions', ['id' => $completion->id]);
    }

    public function test_duplicate_completion_and_item_from_another_version_are_rejected(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        $item = $fixture['version']->items->firstOrFail();
        $action = app(CompleteChecklistItem::class);
        $action->handle($fixture['preparer'], $fixture['workItem'], $item, 'Synthetic first evidence.');

        try {
            $action->handle($fixture['preparer'], $fixture['workItem'], $item, 'Synthetic duplicate evidence.');
            $this->fail('Duplicate completion evidence must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checklistItem', $exception->errors());
        }

        $this->activateFirmMembership($fixture['managerMembership']);
        $otherVersion = app(PublishChecklistVersion::class)->handle(
            $fixture['manager'],
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [['key' => 'new-item', 'label' => 'Synthetic new item']],
        );
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(ValidationException::class);
        $action->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $otherVersion->items->firstOrFail(),
            'Synthetic wrong-version evidence.',
        );
    }

    public function test_reviewer_cannot_complete_preparer_checklist_and_terminal_work_is_closed(): void
    {
        $fixture = $this->fixture();
        $item = $fixture['version']->items->firstOrFail();
        $this->activateFirmMembership($fixture['reviewerMembership']);

        try {
            app(CompleteChecklistItem::class)->handle(
                $fixture['reviewer'],
                $fixture['workItem'],
                $item,
                'Synthetic reviewer attempt.',
            );
            $this->fail('A reviewer must not complete preparer checklist evidence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checklistItem', $exception->errors());
        }

        $this->activateFirmMembership($fixture['managerMembership']);
        $fixture['workItem']->update(['status' => WorkItemStatus::Cancelled]);
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(ValidationException::class);
        app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $item,
            'Synthetic terminal attempt.',
        );
    }

    public function test_preparer_completes_checklist_item_through_livewire(): void
    {
        $fixture = $this->fixture();
        $item = $fixture['version']->items->firstOrFail();
        $this->activateFirmMembership($fixture['preparerMembership']);

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->call('openChecklist', $fixture['workItem']->id)
            ->assertSet('showChecklistModal', true)
            ->assertSee('Published version 1')
            ->assertSee($item->label)
            ->set('checklistItemId', $item->id)
            ->set('checklistEvidenceNote', 'Synthetic Livewire completion evidence.')
            ->call('completeChecklistItem')
            ->assertHasNoErrors()
            ->assertSee('Synthetic Livewire completion evidence.')
            ->assertSee('1 of 2 complete');
    }

    public function test_required_checklist_evidence_blocks_submit_for_review_without_writing_history(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
            'Synthetic preparation start.',
        );

        try {
            app(TransitionWorkItem::class)->handle(
                $fixture['preparer'],
                $fixture['workItem'],
                WorkItemStatus::UnderReview,
                'Synthetic incomplete submission.',
            );
            $this->fail('Required checklist evidence must block review submission.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1 required checklist item', $exception->errors()['targetStatus'][0]);
        }

        $this->assertSame(WorkItemStatus::InPreparation, $fixture['workItem']->refresh()->status);
        $this->assertDatabaseCount('work_item_transitions', 1);
    }

    public function test_optional_item_may_remain_open_after_required_evidence_is_retained(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
            'Synthetic preparation start.',
        );
        app(CompleteChecklistItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            $fixture['version']->items->where('required', true)->firstOrFail(),
            'Synthetic required evidence.',
        );

        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::UnderReview,
            'Synthetic completed submission.',
        );

        $this->assertSame(WorkItemStatus::UnderReview, $fixture['workItem']->refresh()->status);
        $this->assertDatabaseCount('checklist_item_completions', 1);
    }

    public function test_livewire_retains_transition_modal_and_explains_missing_evidence(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
            'Synthetic preparation start.',
        );

        Livewire::actingAs($fixture['preparer'])
            ->test(Index::class)
            ->call('openTransition', $fixture['workItem']->id)
            ->set('targetWorkItemStatus', WorkItemStatus::UnderReview->value)
            ->assertSee('0 of 1 required items are completed')
            ->set('transitionReason', 'Synthetic incomplete Livewire submission.')
            ->call('transitionWork')
            ->assertHasErrors('targetStatus')
            ->assertSet('showTransitionModal', true)
            ->assertSee('open the checklist and complete the remaining items');
    }

    /**
     * @return array{
     * firm: Firm, manager: User, managerMembership: FirmMembership,
     * preparer: User, preparerMembership: FirmMembership,
     * reviewer: User, reviewerMembership: FirmMembership,
     * version: ChecklistVersion, workItem: WorkItem
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
        $version = app(PublishChecklistVersion::class)->handle(
            $manager,
            ChecklistTemplate::CORE_KEY,
            'Synthetic core checklist',
            [
                ['key' => 'source-review', 'label' => 'Review synthetic source records'],
                ['key' => 'preparation-note', 'label' => 'Record synthetic preparation note', 'required' => false],
            ],
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
        )->load('checklist');

        return compact(
            'firm',
            'manager',
            'managerMembership',
            'preparer',
            'preparerMembership',
            'reviewer',
            'reviewerMembership',
            'version',
            'workItem',
        );
    }
}
