<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\AssignmentRole;
use App\Enums\Feature;
use App\Enums\FirmMembershipStatus;
use App\Enums\Permission;
use App\Enums\WorkItemStatus;
use App\Models\AssignmentHistory;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CreateAssignedWorkItem
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
        Obligation $obligation,
        string $preparerMembershipId,
        string $reviewerMembershipId,
        string $managerMembershipId,
        string $reason,
    ): WorkItem {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', WorkItem::class);

        /** @var array{preparer: string, reviewer: string, manager: string, reason: string} $validated */
        $validated = Validator::make(
            [
                'preparer' => $preparerMembershipId,
                'reviewer' => $reviewerMembershipId,
                'manager' => $managerMembershipId,
                'reason' => $reason,
            ],
            [
                'preparer' => ['required', 'string', 'ulid', 'different:reviewer'],
                'reviewer' => ['required', 'string', 'ulid'],
                'manager' => ['required', 'string', 'ulid'],
                'reason' => ['required', 'string', 'max:500'],
            ],
            ['preparer.different' => 'The preparer and reviewer must be different people.'],
        )->validate();

        return DB::transaction(function () use ($actor, $obligation, $validated): WorkItem {
            $lockedObligation = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);

            $primaryExists = WorkItem::query()
                ->whereBelongsTo($lockedObligation)
                ->whereNull('parent_work_item_id')
                ->exists();

            if ($primaryExists) {
                throw ValidationException::withMessages([
                    'workItem' => 'This obligation already has a primary work item.',
                ]);
            }

            $checklistVersion = ChecklistVersion::query()
                ->where('status', 'published')
                ->whereHas(
                    'template',
                    static fn ($query) => $query->where('template_key', ChecklistTemplate::CORE_KEY),
                )
                ->orderByDesc('version')
                ->first();

            if (! $checklistVersion instanceof ChecklistVersion) {
                throw ValidationException::withMessages([
                    'workItem' => 'Publish the core compliance checklist before assigning work.',
                ]);
            }

            $workflowDefinition = WorkflowDefinition::query()
                ->where('definition_key', WorkflowDefinition::CORE_KEY)
                ->where('status', 'published')
                ->orderByDesc('version')
                ->first();

            if (! $workflowDefinition instanceof WorkflowDefinition) {
                throw ValidationException::withMessages([
                    'workItem' => 'Publish the core compliance workflow before assigning work.',
                ]);
            }

            $ids = array_values(array_unique([
                $validated['preparer'],
                $validated['reviewer'],
                $validated['manager'],
            ]));
            $memberships = FirmMembership::query()
                ->where('status', FirmMembershipStatus::Active)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($memberships->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Every assignee must be an active member of this firm.',
                ]);
            }

            $required = [
                AssignmentRole::Preparer->value => [$validated['preparer'], Permission::PrepareWork],
                AssignmentRole::Reviewer->value => [$validated['reviewer'], Permission::ReviewWork],
                AssignmentRole::ResponsibleManager->value => [$validated['manager'], Permission::AssignWork],
            ];

            foreach ($required as $role => [$membershipId, $permission]) {
                $membership = $memberships->get($membershipId);

                if (! $membership instanceof FirmMembership || ! $membership->hasPermission($permission)) {
                    throw ValidationException::withMessages([
                        $role => "The selected {$role} does not have the required permission.",
                    ]);
                }
            }

            $workItem = WorkItem::query()->create([
                'obligation_id' => $lockedObligation->id,
                'parent_work_item_id' => null,
                'primary_obligation_id' => $lockedObligation->id,
                'workflow_definition_id' => $workflowDefinition->id,
                'status' => WorkItemStatus::NotStarted,
                'created_by' => $actor->id,
            ]);
            $assignedAt = now('UTC');
            WorkItemChecklist::query()->create([
                'work_item_id' => $workItem->id,
                'checklist_version_id' => $checklistVersion->id,
                'applied_by' => $actor->id,
                'applied_at' => $assignedAt,
            ]);

            foreach ($required as $role => [$membershipId]) {
                AssignmentHistory::query()->create([
                    'work_item_id' => $workItem->id,
                    'assignment_role' => $role,
                    'assigned_membership_id' => $membershipId,
                    'assigned_by' => $actor->id,
                    'reason' => trim($validated['reason']),
                    'assigned_at' => $assignedAt,
                ]);
            }

            $this->recordAudit->handle(
                action: 'work_item.created_and_assigned',
                actor: $actor,
                auditable: $workItem,
                after: [
                    'obligation_id' => $lockedObligation->id,
                    'workflow_definition_id' => $workflowDefinition->id,
                    'workflow_version' => $workflowDefinition->version,
                    'status' => $workItem->status->value,
                    'preparer_membership_id' => $validated['preparer'],
                    'reviewer_membership_id' => $validated['reviewer'],
                    'responsible_manager_membership_id' => $validated['manager'],
                ],
                reason: trim($validated['reason']),
            );

            return $workItem->load('assignmentHistories.assignedMembership.user');
        }, 3);
    }
}
