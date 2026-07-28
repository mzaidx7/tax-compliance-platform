<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\AssignmentRole;
use App\Enums\Feature;
use App\Enums\ReviewDecision;
use App\Enums\WorkItemStatus;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemTransition;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class DecideWorkItemReview
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        WorkItem $workItem,
        ReviewDecision $decision,
        string $reason,
    ): WorkItemTransition {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('review', $workItem);

        /** @var array{decision: string, reason: string} $validated */
        $validated = Validator::make(
            ['decision' => $decision->value, 'reason' => $reason],
            [
                'decision' => ['required', Rule::enum(ReviewDecision::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $workItem, $decision, $validated): WorkItemTransition {
            $lockedWorkItem = WorkItem::query()
                ->with(['assignmentHistories', 'workflowDefinition.steps'])
                ->lockForUpdate()
                ->findOrFail($workItem->id);

            if ($lockedWorkItem->status !== WorkItemStatus::UnderReview) {
                throw ValidationException::withMessages([
                    'decision' => 'A review decision can only be recorded while work is under review.',
                ]);
            }

            $this->authorizeAssignedReviewer($actor, $lockedWorkItem);

            $targetStatus = $decision->targetStatus();

            if (
                $lockedWorkItem->transitionRoleFor($targetStatus) !== AssignmentRole::Reviewer
                || ! in_array($targetStatus, $lockedWorkItem->allowedTransitions(), true)
            ) {
                throw ValidationException::withMessages([
                    'decision' => 'The pinned workflow does not permit this review decision.',
                ]);
            }

            $fromStatus = $lockedWorkItem->status;
            $transitionedAt = now('UTC');

            $transition = WorkItemTransition::query()->create([
                'work_item_id' => $lockedWorkItem->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'transitioned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'transitioned_at' => $transitionedAt,
            ]);

            $lockedWorkItem->update(['status' => $targetStatus]);

            $this->recordAudit->handle(
                action: 'work_item.review_decided',
                actor: $actor,
                auditable: $lockedWorkItem,
                before: ['status' => $fromStatus->value],
                after: ['status' => $targetStatus->value, 'decision' => $decision->value],
                reason: trim($validated['reason']),
            );

            return $transition->refresh();
        }, 3);
    }

    private function authorizeAssignedReviewer(User $actor, WorkItem $workItem): void
    {
        $actorMembership = $this->firmContext->membership();
        $assignedMembershipId = $workItem->currentAssignment(AssignmentRole::Reviewer)?->assigned_membership_id;

        if (
            ! $actorMembership instanceof FirmMembership
            || $actorMembership->user_id !== $actor->id
            || $actorMembership->id !== $assignedMembershipId
        ) {
            throw ValidationException::withMessages([
                'decision' => 'Only the currently assigned reviewer can record this decision.',
            ]);
        }
    }
}
