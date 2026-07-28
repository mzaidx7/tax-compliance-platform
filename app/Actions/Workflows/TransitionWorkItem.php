<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\AssignmentRole;
use App\Enums\Feature;
use App\Enums\WorkItemStatus;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Models\WorkItemTransition;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class TransitionWorkItem
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
        WorkItemStatus $targetStatus,
        string $reason,
    ): WorkItemTransition {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('transition', $workItem);

        /** @var array{targetStatus: string, reason: string} $validated */
        $validated = Validator::make(
            ['targetStatus' => $targetStatus->value, 'reason' => $reason],
            [
                'targetStatus' => ['required', Rule::enum(WorkItemStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $workItem, $targetStatus, $validated): WorkItemTransition {
            $lockedWorkItem = WorkItem::query()
                ->with([
                    'assignmentHistories',
                    'workflowDefinition.steps',
                    'checklist.version.items',
                    'checklist.completions',
                ])
                ->lockForUpdate()
                ->findOrFail($workItem->id);
            $fromStatus = $lockedWorkItem->status;

            if (! in_array($targetStatus, $lockedWorkItem->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'targetStatus' => "Work cannot move from {$fromStatus->label()} to {$targetStatus->label()}.",
                ]);
            }

            $requiredRole = $lockedWorkItem->transitionRoleFor($targetStatus);

            if (! $requiredRole instanceof AssignmentRole) {
                throw ValidationException::withMessages([
                    'targetStatus' => 'The pinned workflow does not define this transition.',
                ]);
            }
            $this->authorizeAssignedActor($actor, $lockedWorkItem, $requiredRole);
            $this->enforceChecklistGate($lockedWorkItem, $targetStatus);
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
                action: 'work_item.status_transitioned',
                actor: $actor,
                auditable: $lockedWorkItem,
                before: ['status' => $fromStatus->value],
                after: ['status' => $targetStatus->value],
                reason: trim($validated['reason']),
            );

            return $transition->refresh();
        }, 3);
    }

    private function enforceChecklistGate(WorkItem $workItem, WorkItemStatus $targetStatus): void
    {
        if ($targetStatus !== WorkItemStatus::UnderReview) {
            return;
        }

        $checklist = $workItem->checklist;

        if (! $checklist instanceof WorkItemChecklist) {
            throw ValidationException::withMessages([
                'targetStatus' => 'This work has no pinned checklist. Ask a manager to correct the work setup before submitting for review.',
            ]);
        }

        $requiredItemIds = $checklist->version->items
            ->where('required', true)
            ->pluck('id');
        $completedItemIds = $checklist->completions->pluck('checklist_item_id');
        $missingCount = $requiredItemIds->diff($completedItemIds)->count();

        if ($missingCount > 0) {
            $itemLabel = $missingCount === 1 ? 'item' : 'items';

            throw ValidationException::withMessages([
                'targetStatus' => "Complete the remaining {$missingCount} required checklist {$itemLabel} before submitting for review. Open Work checklist to add evidence.",
            ]);
        }
    }

    private function authorizeAssignedActor(
        User $actor,
        WorkItem $workItem,
        AssignmentRole $requiredRole,
    ): void {
        $actorMembership = $this->firmContext->membership();
        $assignedMembershipId = $workItem->currentAssignment($requiredRole)?->assigned_membership_id;

        if (
            ! $actorMembership instanceof FirmMembership
            || $actorMembership->user_id !== $actor->id
            || $actorMembership->id !== $assignedMembershipId
        ) {
            throw ValidationException::withMessages([
                'targetStatus' => "Only the currently assigned {$requiredRole->label()} can make this transition.",
            ]);
        }
    }
}
