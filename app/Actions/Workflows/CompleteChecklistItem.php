<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Enums\AssignmentRole;
use App\Enums\Feature;
use App\Enums\WorkItemStatus;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemCompletion;
use App\Models\FirmMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CompleteChecklistItem
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        WorkItem $workItem,
        ChecklistItem $item,
        string $evidenceNote,
    ): ChecklistItemCompletion {
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $this->firmContext->firmId())) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('transition', $workItem);
        /** @var array{evidenceNote: string} $validated */
        $validated = Validator::make(
            ['evidenceNote' => $evidenceNote],
            ['evidenceNote' => ['required', 'string', 'max:500']],
        )->validate();

        return DB::transaction(function () use ($actor, $workItem, $item, $validated): ChecklistItemCompletion {
            $lockedWorkItem = WorkItem::query()
                ->with(['assignmentHistories', 'checklist'])
                ->lockForUpdate()
                ->findOrFail($workItem->id);
            $checklist = $lockedWorkItem->checklist;

            if (! $checklist instanceof WorkItemChecklist || $item->checklist_version_id !== $checklist->checklist_version_id) {
                throw ValidationException::withMessages([
                    'checklistItem' => 'The checklist item does not belong to the version pinned to this work.',
                ]);
            }

            if (in_array($lockedWorkItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'checklistItem' => 'Checklist evidence cannot be added to terminal work.',
                ]);
            }

            $membership = $this->firmContext->membership();
            $preparerId = $lockedWorkItem->currentAssignment(AssignmentRole::Preparer)?->assigned_membership_id;

            if (! $membership instanceof FirmMembership || $membership->id !== $preparerId) {
                throw ValidationException::withMessages([
                    'checklistItem' => 'Only the currently assigned preparer can complete checklist items.',
                ]);
            }

            if (ChecklistItemCompletion::query()
                ->where('work_item_checklist_id', $checklist->id)
                ->where('checklist_item_id', $item->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'checklistItem' => 'This checklist item already has retained completion evidence.',
                ]);
            }

            $completion = ChecklistItemCompletion::query()->create([
                'work_item_checklist_id' => $checklist->id,
                'checklist_item_id' => $item->id,
                'completed_by' => $actor->id,
                'evidence_note' => trim($validated['evidenceNote']),
                'completed_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'checklist.item_completed',
                actor: $actor,
                auditable: $lockedWorkItem,
                after: [
                    'checklist_version_id' => $checklist->checklist_version_id,
                    'checklist_item_id' => $item->id,
                ],
            );

            return $completion->refresh();
        }, 3);
    }
}
