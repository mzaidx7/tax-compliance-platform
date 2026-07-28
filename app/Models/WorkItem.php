<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssignmentRole;
use App\Enums\FirmMembershipStatus;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\Concerns\BelongsToFirm;
use Database\Factories\WorkItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $firm_id
 * @property string $obligation_id
 * @property string|null $parent_work_item_id
 * @property string|null $primary_obligation_id
 * @property string $workflow_definition_id
 * @property WorkItemStatus $status
 * @property RiskLevel $risk_status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'obligation_id',
    'parent_work_item_id',
    'primary_obligation_id',
    'workflow_definition_id',
    'status',
    'risk_status',
    'created_by',
])]
final class WorkItem extends Model
{
    /** @use HasFactory<WorkItemFactory> */
    use BelongsToFirm, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Obligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<WorkflowDefinition, $this> */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    /**
     * @return HasMany<AssignmentHistory, $this>
     */
    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(AssignmentHistory::class);
    }

    /** @return HasMany<WorkItemTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkItemTransition::class);
    }

    /** @return HasOne<WorkItemChecklist, $this> */
    public function checklist(): HasOne
    {
        return $this->hasOne(WorkItemChecklist::class);
    }

    /** @return HasMany<WorkItemRiskChange, $this> */
    public function riskChanges(): HasMany
    {
        return $this->hasMany(WorkItemRiskChange::class);
    }

    /** @return HasMany<DocumentEvidence, $this> */
    public function documentEvidence(): HasMany
    {
        return $this->hasMany(DocumentEvidence::class);
    }

    /**
     * The completed work item this follow-up corrects, when this is a follow-up.
     *
     * @return BelongsTo<WorkItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_work_item_id');
    }

    /**
     * Linked follow-up work items created by a controlled reopen.
     *
     * @return HasMany<WorkItem, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(self::class, 'parent_work_item_id');
    }

    public function isFollowUp(): bool
    {
        return $this->parent_work_item_id !== null;
    }

    /**
     * The user currently accountable for this work, when they are still active.
     *
     * Operational notifications address this member. Returning null means there
     * is nobody valid to notify, and the caller must skip rather than guess.
     */
    public function responsibleManagerUser(): ?User
    {
        $membership = $this->currentAssignment(AssignmentRole::ResponsibleManager)?->assignedMembership;

        if (! $membership instanceof FirmMembership || $membership->status !== FirmMembershipStatus::Active) {
            return null;
        }

        return $membership->user;
    }

    public function currentAssignment(AssignmentRole $role): ?AssignmentHistory
    {
        return $this->assignmentHistories
            ->where('assignment_role', $role)
            ->sortByDesc('id')
            ->first();
    }

    /** @return list<WorkItemStatus> */
    public function allowedTransitions(): array
    {
        $transitions = [];

        foreach ($this->workflowDefinition->steps as $step) {
            if ($step->from_status === $this->status) {
                $transitions[] = $step->to_status;
            }
        }

        return $transitions;
    }

    public function transitionRoleFor(WorkItemStatus $target): ?AssignmentRole
    {
        foreach ($this->workflowDefinition->steps as $step) {
            if ($step->from_status === $this->status && $step->to_status === $target) {
                return $step->assignment_role;
            }
        }

        return null;
    }

    /**
     * Transitions available through the generic transition dialog for the given membership.
     *
     * Reviewer decisions from under_review are made exclusively through the dedicated
     * review action, so every edge from under_review other than Cancelled is excluded
     * here. This must stay the single source of truth for that exclusion: both the
     * Livewire transition dialog and the work-register button visibility check call
     * this method so they never disagree about what is reachable.
     *
     * @return list<WorkItemStatus>
     */
    public function genericTransitionTargetsFor(?string $membershipId): array
    {
        return array_values(array_filter(
            $this->allowedTransitions(),
            function (WorkItemStatus $target) use ($membershipId): bool {
                if ($this->status === WorkItemStatus::UnderReview && $target !== WorkItemStatus::Cancelled) {
                    return false;
                }

                $role = $this->transitionRoleFor($target);

                return $role instanceof AssignmentRole
                    && $this->currentAssignment($role)?->assigned_membership_id === $membershipId;
            },
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkItemStatus::class,
            'risk_status' => RiskLevel::class,
        ];
    }
}
