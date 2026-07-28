<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use App\Models\WorkItem;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class MigrateWorkItemWorkflowVersion
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
        string $targetWorkflowDefinitionId,
        string $reason,
    ): WorkItem {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('update', $workItem);

        /** @var array{targetDefinitionId: string, reason: string} $validated */
        $validated = Validator::make(
            ['targetDefinitionId' => $targetWorkflowDefinitionId, 'reason' => $reason],
            [
                'targetDefinitionId' => ['required', 'string', 'ulid'],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $workItem, $validated): WorkItem {
            $lockedWorkItem = WorkItem::query()
                ->with('workflowDefinition')
                ->lockForUpdate()
                ->findOrFail($workItem->id);

            if (in_array($lockedWorkItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'targetDefinitionId' => 'Completed or cancelled work cannot be migrated to another workflow version.',
                ]);
            }

            $currentDefinition = $lockedWorkItem->workflowDefinition;

            $targetDefinition = WorkflowDefinition::query()
                ->with('steps')
                ->where('id', $validated['targetDefinitionId'])
                ->where('definition_key', $currentDefinition->definition_key)
                ->where('status', 'published')
                ->first();

            if (
                ! $targetDefinition instanceof WorkflowDefinition
                || $targetDefinition->version <= $currentDefinition->version
            ) {
                throw ValidationException::withMessages([
                    'targetDefinitionId' => 'Select a later published version of the same workflow.',
                ]);
            }

            $hasOutgoingStep = $targetDefinition->steps
                ->contains(static fn (WorkflowStep $step): bool => $step->from_status === $lockedWorkItem->status);

            if (! $hasOutgoingStep) {
                throw ValidationException::withMessages([
                    'targetDefinitionId' => 'The selected workflow version defines no transition from the current work status.',
                ]);
            }

            $previousDefinitionId = $lockedWorkItem->workflow_definition_id;
            $previousVersion = $currentDefinition->version;

            $lockedWorkItem->update(['workflow_definition_id' => $targetDefinition->id]);

            $this->recordAudit->handle(
                action: 'work_item.workflow_version_migrated',
                actor: $actor,
                auditable: $lockedWorkItem,
                before: [
                    'workflow_definition_id' => $previousDefinitionId,
                    'workflow_version' => $previousVersion,
                ],
                after: [
                    'workflow_definition_id' => $targetDefinition->id,
                    'workflow_version' => $targetDefinition->version,
                ],
                reason: trim($validated['reason']),
            );

            return $lockedWorkItem->refresh()->load('workflowDefinition.steps');
        }, 3);
    }
}
