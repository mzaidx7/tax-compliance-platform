<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\AssignmentRole;
use App\Enums\Feature;
use App\Enums\FirmMembershipStatus;
use App\Enums\Permission;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\AssignmentHistory;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVersion;
use App\Models\FirmMembership;
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

/**
 * Reopen a completed work item as a linked follow-up.
 *
 * The original work item, its transition history, checklist evidence and
 * assignment history are never changed. The follow-up is a new work item with
 * its own lifecycle, pinned to the latest published workflow and checklist
 * versions. Filing, payment, tax and risk state are untouched.
 */
final readonly class ReopenWorkItem
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, WorkItem $original, string $reason): WorkItem
    {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', WorkItem::class);

        // The `create` ability is class level and cannot see which firm the
        // original belongs to. Resolve it through the tenant scope first so a
        // cross-firm attempt fails as an authorization error, consistently with
        // every other record boundary, rather than as a missing model.
        $scopedOriginal = WorkItem::query()->whereKey($original->id)->first();

        if (! $scopedOriginal instanceof WorkItem) {
            throw new AuthorizationException('The selected work item does not belong to the active firm.');
        }

        /** @var array{reason: string} $validated */
        $validated = Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:500']],
        )->validate();

        return DB::transaction(function () use ($actor, $original, $validated): WorkItem {
            $lockedOriginal = WorkItem::query()
                ->with('assignmentHistories')
                ->lockForUpdate()
                ->findOrFail($original->id);

            if ($lockedOriginal->status !== WorkItemStatus::Completed) {
                throw ValidationException::withMessages([
                    'reopen' => 'Only completed work can be reopened as a follow-up.',
                ]);
            }

            if ($lockedOriginal->isFollowUp()) {
                throw ValidationException::withMessages([
                    'reopen' => 'A follow-up cannot itself be reopened. Reopen the original work instead.',
                ]);
            }

            $openFollowUpExists = WorkItem::query()
                ->where('parent_work_item_id', $lockedOriginal->id)
                ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled])
                ->exists();

            if ($openFollowUpExists) {
                throw ValidationException::withMessages([
                    'reopen' => 'This work already has an open follow-up. Complete or cancel it before reopening again.',
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
                    'reopen' => 'Publish the core compliance checklist before reopening work.',
                ]);
            }

            $workflowDefinition = WorkflowDefinition::query()
                ->where('definition_key', WorkflowDefinition::CORE_KEY)
                ->where('status', 'published')
                ->orderByDesc('version')
                ->first();

            if (! $workflowDefinition instanceof WorkflowDefinition) {
                throw ValidationException::withMessages([
                    'reopen' => 'Publish the core compliance workflow before reopening work.',
                ]);
            }

            $owners = $this->currentOwners($lockedOriginal);

            $followUp = WorkItem::query()->create([
                'obligation_id' => $lockedOriginal->obligation_id,
                'parent_work_item_id' => $lockedOriginal->id,
                'primary_obligation_id' => null,
                'workflow_definition_id' => $workflowDefinition->id,
                'status' => WorkItemStatus::NotStarted,
                'risk_status' => RiskLevel::Unassessed,
                'created_by' => $actor->id,
            ]);
            $assignedAt = now('UTC');

            WorkItemChecklist::query()->create([
                'work_item_id' => $followUp->id,
                'checklist_version_id' => $checklistVersion->id,
                'applied_by' => $actor->id,
                'applied_at' => $assignedAt,
            ]);

            foreach ($owners as $role => $membershipId) {
                AssignmentHistory::query()->create([
                    'work_item_id' => $followUp->id,
                    'assignment_role' => $role,
                    'assigned_membership_id' => $membershipId,
                    'assigned_by' => $actor->id,
                    'reason' => trim($validated['reason']),
                    'assigned_at' => $assignedAt,
                ]);
            }

            $this->recordAudit->handle(
                action: 'work_item.reopened',
                actor: $actor,
                auditable: $followUp,
                after: [
                    'parent_work_item_id' => $lockedOriginal->id,
                    'obligation_id' => $lockedOriginal->obligation_id,
                    'workflow_definition_id' => $workflowDefinition->id,
                    'workflow_version' => $workflowDefinition->version,
                    'status' => WorkItemStatus::NotStarted->value,
                ],
                reason: trim($validated['reason']),
            );

            return $followUp->load('assignmentHistories.assignedMembership.user');
        }, 3);
    }

    /**
     * Carry the original's current owners forward, re-checking each is still
     * an active member of this firm with the permission the role requires.
     *
     * @return array<string, string>
     */
    private function currentOwners(WorkItem $original): array
    {
        $required = [
            AssignmentRole::Preparer->value => Permission::PrepareWork,
            AssignmentRole::Reviewer->value => Permission::ReviewWork,
            AssignmentRole::ResponsibleManager->value => Permission::AssignWork,
        ];
        $owners = [];

        foreach ($required as $role => $permission) {
            $membershipId = $original
                ->currentAssignment(AssignmentRole::from($role))
                ?->assigned_membership_id;

            if (! is_string($membershipId)) {
                throw ValidationException::withMessages([
                    'reopen' => "The original work has no current {$role}. Correct the work setup before reopening.",
                ]);
            }

            $membership = FirmMembership::query()
                ->where('status', FirmMembershipStatus::Active)
                ->find($membershipId);

            if (! $membership instanceof FirmMembership || ! $membership->hasPermission($permission)) {
                throw ValidationException::withMessages([
                    'reopen' => "The current {$role} is no longer an active member with the required permission. Reassign the original before reopening.",
                ]);
            }

            $owners[$role] = $membershipId;
        }

        return $owners;
    }
}
