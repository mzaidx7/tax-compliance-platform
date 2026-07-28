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
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ReassignWorkItem
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        WorkItem $workItem,
        AssignmentRole $role,
        string $replacementMembershipId,
        string $reason,
    ): AssignmentHistory {
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $this->firmContext->firmId())) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('update', $workItem);
        /** @var array{replacement: string, reason: string} $validated */
        $validated = Validator::make(
            ['replacement' => $replacementMembershipId, 'reason' => $reason],
            [
                'replacement' => ['required', 'string', 'ulid'],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $role, $validated, $workItem): AssignmentHistory {
            $lockedWorkItem = WorkItem::query()
                ->with('assignmentHistories')
                ->lockForUpdate()
                ->findOrFail($workItem->id);

            if (in_array($lockedWorkItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'reassignment' => 'Completed or cancelled work cannot be reassigned.',
                ]);
            }

            $replacement = FirmMembership::query()
                ->where('status', FirmMembershipStatus::Active)
                ->lockForUpdate()
                ->find($validated['replacement']);
            $requiredPermission = match ($role) {
                AssignmentRole::Preparer => Permission::PrepareWork,
                AssignmentRole::Reviewer => Permission::ReviewWork,
                AssignmentRole::ResponsibleManager => Permission::AssignWork,
            };

            if (! $replacement instanceof FirmMembership || ! $replacement->hasPermission($requiredPermission)) {
                throw ValidationException::withMessages([
                    'replacement' => "The replacement {$role->label()} must be an active member with the required permission.",
                ]);
            }

            $current = $lockedWorkItem->currentAssignment($role);

            if ($current?->assigned_membership_id === $replacement->id) {
                throw ValidationException::withMessages([
                    'replacement' => "The selected member is already the current {$role->label()}.",
                ]);
            }

            $oppositeRole = match ($role) {
                AssignmentRole::Preparer => AssignmentRole::Reviewer,
                AssignmentRole::Reviewer => AssignmentRole::Preparer,
                AssignmentRole::ResponsibleManager => null,
            };

            if (
                $oppositeRole instanceof AssignmentRole
                && $lockedWorkItem->currentAssignment($oppositeRole)?->assigned_membership_id === $replacement->id
            ) {
                throw ValidationException::withMessages([
                    'replacement' => 'The preparer and reviewer must remain different people.',
                ]);
            }

            $history = AssignmentHistory::query()->create([
                'work_item_id' => $lockedWorkItem->id,
                'assignment_role' => $role,
                'assigned_membership_id' => $replacement->id,
                'assigned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'assigned_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'work_item.reassigned',
                actor: $actor,
                auditable: $lockedWorkItem,
                before: [
                    'assignment_role' => $role->value,
                    'membership_id' => $current?->assigned_membership_id,
                ],
                after: [
                    'assignment_role' => $role->value,
                    'membership_id' => $replacement->id,
                ],
                reason: trim($validated['reason']),
            );

            return $history->load('assignedMembership.user');
        }, 3);
    }
}
