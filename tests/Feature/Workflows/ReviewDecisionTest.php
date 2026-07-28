<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CompleteChecklistItem;
use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\DecideWorkItemReview;
use App\Actions\Workflows\PublishChecklistVersion;
use App\Actions\Workflows\PublishCoreWorkflowVersion;
use App\Actions\Workflows\TransitionWorkItem;
use App\Enums\FirmRole;
use App\Enums\ReviewDecision;
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

final class ReviewDecisionTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_assigned_reviewer_approves_work_with_append_only_evidence_and_audit(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['reviewerMembership']);

        $transition = app(DecideWorkItemReview::class)->handle(
            $fixture['reviewer'],
            $fixture['workItem'],
            ReviewDecision::Approve,
            'Synthetic approval reason.',
        );

        $this->assertSame(WorkItemStatus::UnderReview, $transition->from_status);
        $this->assertSame(WorkItemStatus::AwaitingClientApproval, $transition->to_status);
        $this->assertSame($fixture['reviewer']->id, $transition->transitioned_by);
        $this->assertSame(WorkItemStatus::AwaitingClientApproval, $fixture['workItem']->refresh()->status);

        $audit = AuditLog::query()->where('action', 'work_item.review_decided')->sole();
        $this->assertSame(WorkItemStatus::UnderReview->value, $audit->before_values['status']);
        $this->assertSame(WorkItemStatus::AwaitingClientApproval->value, $audit->after_values['status']);
        $this->assertSame(ReviewDecision::Approve->value, $audit->after_values['decision']);
    }

    public function test_assigned_reviewer_returns_work_explicitly_to_the_assigned_preparer(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['reviewerMembership']);

        $transition = app(DecideWorkItemReview::class)->handle(
            $fixture['reviewer'],
            $fixture['workItem'],
            ReviewDecision::Return,
            'Synthetic return reason.',
        );

        $this->assertSame(WorkItemStatus::ReturnedForChanges, $transition->to_status);
        $this->assertSame(WorkItemStatus::ReturnedForChanges, $fixture['workItem']->refresh()->status);

        $this->activateFirmMembership($fixture['preparerMembership']);
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::InPreparation,
            'Synthetic re-preparation after return.',
        );

        $this->assertSame(WorkItemStatus::InPreparation, $fixture['workItem']->refresh()->status);
    }

    public function test_unassigned_reviewer_cannot_decide(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $otherReviewer = User::factory()->create();
        $otherMembership = $this->createFirmMembership($fixture['firm'], $otherReviewer, FirmRole::Reviewer);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(ValidationException::class);
        app(DecideWorkItemReview::class)->handle(
            $otherReviewer,
            $fixture['workItem'],
            ReviewDecision::Approve,
            'Synthetic unauthorised attempt.',
        );
    }

    public function test_preparer_cannot_decide_own_submitted_work(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['preparerMembership']);

        $this->expectException(AuthorizationException::class);
        app(DecideWorkItemReview::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            ReviewDecision::Approve,
            'Synthetic preparer attempt.',
        );
    }

    public function test_decision_is_rejected_when_work_is_not_under_review(): void
    {
        $fixture = $this->fixture();
        $this->activateFirmMembership($fixture['reviewerMembership']);

        try {
            app(DecideWorkItemReview::class)->handle(
                $fixture['reviewer'],
                $fixture['workItem'],
                ReviewDecision::Approve,
                'Synthetic premature decision.',
            );
            $this->fail('A review decision must require the under review state.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('decision', $exception->errors());
        }

        $this->assertDatabaseCount('work_item_transitions', 0);
    }

    public function test_blank_review_reason_is_rejected_without_writing_history(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['reviewerMembership']);

        try {
            app(DecideWorkItemReview::class)->handle(
                $fixture['reviewer'],
                $fixture['workItem'],
                ReviewDecision::Approve,
                ' ',
            );
            $this->fail('A blank review reason must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(WorkItemStatus::UnderReview, $fixture['workItem']->refresh()->status);
    }

    public function test_a_reviewer_from_another_firm_cannot_decide_this_firms_work_item(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);

        $otherFirm = Firm::factory()->create();
        $otherReviewer = User::factory()->create();
        $otherMembership = $this->createFirmMembership($otherFirm, $otherReviewer, FirmRole::Reviewer);
        $this->activateFirmMembership($otherMembership);

        $this->expectException(AuthorizationException::class);
        app(DecideWorkItemReview::class)->handle(
            $otherReviewer,
            $fixture['workItem'],
            ReviewDecision::Approve,
            'Synthetic cross-firm attempt.',
        );
    }

    public function test_assigned_reviewer_decides_through_livewire_interface(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['reviewerMembership']);

        Livewire::actingAs($fixture['reviewer'])
            ->test(Index::class)
            ->call('openReview', $fixture['workItem']->id)
            ->assertSet('showReviewModal', true)
            ->set('reviewDecision', ReviewDecision::Return->value)
            ->set('reviewReason', 'Synthetic Livewire return decision.')
            ->call('decideReview')
            ->assertHasNoErrors()
            ->assertSet('showReviewModal', false)
            ->assertSee('Work: Returned for changes');

        $this->assertDatabaseHas('work_item_transitions', [
            'work_item_id' => $fixture['workItem']->id,
            'to_status' => WorkItemStatus::ReturnedForChanges->value,
        ]);
    }

    public function test_reviewer_without_manager_access_does_not_see_a_dead_end_update_work_button(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['reviewerMembership']);

        Livewire::actingAs($fixture['reviewer'])
            ->test(Index::class)
            ->assertSee('Decide review')
            ->assertDontSeeHtml("wire:click=\"openTransition('{$fixture['workItem']->id}')\"");
    }

    public function test_responsible_manager_still_sees_update_work_to_cancel_under_review_work(): void
    {
        $fixture = $this->fixture();
        $this->submitForReview($fixture);
        $this->activateFirmMembership($fixture['managerMembership']);

        Livewire::actingAs($fixture['manager'])
            ->test(Index::class)
            ->assertSeeHtml("wire:click=\"openTransition('{$fixture['workItem']->id}')\"");
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
        )->load('checklist.version.items');

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

    /**
     * @param  array{
     *   preparer: User,
     *   preparerMembership: FirmMembership,
     *   workItem: WorkItem,
     * }  $fixture
     */
    private function submitForReview(array $fixture): void
    {
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
            $fixture['workItem']->checklist->version->items->firstOrFail(),
            'Synthetic checklist evidence before review.',
        );
        app(TransitionWorkItem::class)->handle(
            $fixture['preparer'],
            $fixture['workItem'],
            WorkItemStatus::UnderReview,
            'Synthetic submission for review.',
        );
    }
}
