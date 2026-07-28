<?php

declare(strict_types=1);

namespace App\Livewire\Obligations;

use App\Actions\Compliance\CreateManualObligation;
use App\Actions\Compliance\DisposeObligation;
use App\Actions\Compliance\OverrideObligationDeadline;
use App\Actions\Filings\CreateFilingRecord;
use App\Actions\Filings\TransitionFilingRecord;
use App\Actions\Payments\CreatePaymentRecord;
use App\Actions\Payments\TransitionPaymentRecord;
use App\Actions\Taxes\AmendTaxRecord;
use App\Actions\Taxes\CreateTaxRecord;
use App\Actions\Workflows\CompleteChecklistItem;
use App\Actions\Workflows\CreateAssignedWorkItem;
use App\Actions\Workflows\DecideWorkItemReview;
use App\Actions\Workflows\MigrateWorkItemWorkflowVersion;
use App\Actions\Workflows\ReassignWorkItem;
use App\Actions\Workflows\ReopenWorkItem;
use App\Actions\Workflows\SetWorkItemRiskStatus;
use App\Actions\Workflows\TransitionWorkItem;
use App\Enums\AssignmentRole;
use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Enums\FilingStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\ObligationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ReviewDecision;
use App\Enums\RiskLevel;
use App\Enums\TaxRecordStatus;
use App\Enums\TaxType;
use App\Enums\WorkItemStatus;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\FilingRecord;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\TaxRecord;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkItem;
use App\Models\WorkItemChecklist;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Manual obligations')]
final class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $clientId = '';

    public string $obligationType = '';

    public string $periodLabel = '';

    public string $statutoryDueDate = '';

    public string $internalTargetDate = '';

    public string $sourceReference = '';

    public string $lastVerifiedOn = '';

    public bool $showDeadlineOverrideModal = false;

    #[Locked]
    public string $deadlineOverrideObligationId = '';

    public string $deadlineOverrideLabel = '';

    public string $deadlineOverrideDate = '';

    public string $deadlineOverrideReason = '';

    public bool $showDispositionModal = false;

    #[Locked]
    public string $dispositionObligationId = '';

    public string $dispositionLabel = '';

    public string $dispositionStatus = '';

    public string $replacementObligationId = '';

    public string $dispositionReason = '';

    public bool $showAssignmentModal = false;

    #[Locked]
    public string $selectedObligationId = '';

    public string $selectedObligationLabel = '';

    public string $preparerMembershipId = '';

    public string $reviewerMembershipId = '';

    public string $managerMembershipId = '';

    public string $assignmentReason = '';

    public bool $showTransitionModal = false;

    #[Locked]
    public string $selectedWorkItemId = '';

    public string $selectedWorkItemLabel = '';

    public string $selectedWorkItemStatus = '';

    public string $targetWorkItemStatus = '';

    public string $transitionReason = '';

    #[Locked]
    public int $selectedRequiredChecklistTotal = 0;

    #[Locked]
    public int $selectedRequiredChecklistCompleted = 0;

    public bool $showChecklistModal = false;

    #[Locked]
    public string $checklistWorkItemId = '';

    public string $checklistWorkItemLabel = '';

    public string $checklistItemId = '';

    public string $checklistEvidenceNote = '';

    public bool $showReviewModal = false;

    #[Locked]
    public string $reviewWorkItemId = '';

    public string $reviewWorkItemLabel = '';

    public string $reviewDecision = '';

    public string $reviewReason = '';

    public bool $showReassignmentModal = false;

    #[Locked]
    public string $reassignmentWorkItemId = '';

    public string $reassignmentWorkItemLabel = '';

    public string $reassignmentRole = '';

    public string $replacementMembershipId = '';

    public string $reassignmentReason = '';

    /** @var array<string, string> */
    public array $currentOwnerNames = [];

    public bool $showFilingModal = false;

    #[Locked]
    public string $filingObligationId = '';

    #[Locked]
    public string $filingRecordId = '';

    public string $filingWorkLabel = '';

    public string $filingStatus = '';

    public string $filingReference = '';

    public string $filingFiledOn = '';

    public string $filingReason = '';

    public bool $showPaymentModal = false;

    #[Locked]
    public string $paymentObligationId = '';

    #[Locked]
    public string $paymentRecordId = '';

    public string $paymentWorkLabel = '';

    public string $paymentStatus = '';

    public string $paymentReference = '';

    public string $paymentPaidOn = '';

    public string $paymentReason = '';

    public bool $showTaxModal = false;

    #[Locked]
    public string $taxObligationId = '';

    #[Locked]
    public string $taxRecordId = '';

    public string $taxWorkLabel = '';

    public string $taxType = '';

    public string $taxPeriodLabel = '';

    public string $taxCurrency = 'AED';

    public string $taxTaxableAmount = '';

    public string $taxTaxAmount = '';

    public string $taxTargetStatus = '';

    public string $taxReason = '';

    public bool $showMigrationModal = false;

    #[Locked]
    public string $migrationWorkItemId = '';

    public string $migrationWorkItemLabel = '';

    #[Locked]
    public int $migrationCurrentVersion = 0;

    public string $migrationTargetDefinitionId = '';

    public string $migrationReason = '';

    public bool $showRiskModal = false;

    #[Locked]
    public string $riskWorkItemId = '';

    public string $riskWorkItemLabel = '';

    public string $riskLevel = '';

    public string $riskReason = '';

    public bool $showReopenModal = false;

    #[Locked]
    public string $reopenWorkItemId = '';

    public string $reopenWorkItemLabel = '';

    public string $reopenReason = '';

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::ComplianceOperations, $firmContext->firmId()),
            404,
        );

        Gate::authorize('viewAny', Obligation::class);
        $this->lastVerifiedOn = now('Asia/Dubai')->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createObligation(CreateManualObligation $createManualObligation): void
    {
        $obligation = $createManualObligation->handle($this->currentUser(), [
            'clientId' => $this->clientId,
            'obligationType' => $this->obligationType,
            'periodLabel' => $this->periodLabel,
            'statutoryDueDate' => $this->statutoryDueDate,
            'internalTargetDate' => $this->internalTargetDate,
            'sourceReference' => $this->sourceReference,
            'lastVerifiedOn' => $this->lastVerifiedOn,
        ]);

        $this->reset(
            'clientId',
            'obligationType',
            'periodLabel',
            'statutoryDueDate',
            'internalTargetDate',
            'sourceReference',
        );
        $this->lastVerifiedOn = now('Asia/Dubai')->toDateString();
        $this->resetErrorBag();
        $this->resetPage();
        unset($this->obligations);

        Flux::toast(
            variant: 'success',
            text: "Manual obligation created for {$obligation->client->legal_name}.",
        );
    }

    public function openDeadlineOverride(string $obligationId): void
    {
        $obligation = Obligation::query()->findOrFail($obligationId);
        Gate::authorize('update', $obligation);

        $this->resetValidation();
        $this->deadlineOverrideObligationId = $obligation->id;
        $this->deadlineOverrideLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->deadlineOverrideDate = $obligation->effectiveDueDate()->toDateString();
        $this->deadlineOverrideReason = '';
        $this->showDeadlineOverrideModal = true;
    }

    public function overrideDeadline(OverrideObligationDeadline $overrideObligationDeadline): void
    {
        $obligation = Obligation::query()->findOrFail($this->deadlineOverrideObligationId);
        $overrideObligationDeadline->handle($this->currentUser(), $obligation, [
            'effectiveDueDate' => $this->deadlineOverrideDate,
            'reason' => $this->deadlineOverrideReason,
        ]);

        $this->closeDeadlineOverrideModal();
        unset($this->obligations);
        Flux::toast(
            variant: 'success',
            text: __('Effective deadline updated. The statutory date remains unchanged.'),
        );
    }

    public function closeDeadlineOverrideModal(): void
    {
        $this->reset(
            'showDeadlineOverrideModal',
            'deadlineOverrideObligationId',
            'deadlineOverrideLabel',
            'deadlineOverrideDate',
            'deadlineOverrideReason',
        );
        $this->resetValidation();
    }

    public function openDisposition(string $obligationId): void
    {
        $obligation = Obligation::query()->with('client')->findOrFail($obligationId);
        Gate::authorize('update', $obligation);
        $this->resetValidation();
        $this->dispositionObligationId = $obligation->id;
        $this->dispositionLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->dispositionStatus = '';
        $this->replacementObligationId = '';
        $this->dispositionReason = '';
        $this->showDispositionModal = true;
    }

    public function disposeObligation(DisposeObligation $action): void
    {
        $obligation = Obligation::query()->findOrFail($this->dispositionObligationId);
        $action->handle($this->currentUser(), $obligation, [
            'status' => $this->dispositionStatus,
            'replacementObligationId' => $this->replacementObligationId === '' ? null : $this->replacementObligationId,
            'reason' => $this->dispositionReason,
        ]);
        $this->closeDispositionModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: __('Obligation disposition recorded.'));
    }

    public function closeDispositionModal(): void
    {
        $this->reset('showDispositionModal', 'dispositionObligationId', 'dispositionLabel', 'dispositionStatus', 'replacementObligationId', 'dispositionReason');
        $this->resetValidation();
    }

    public function openAssignment(string $obligationId): void
    {
        Gate::authorize('create', WorkItem::class);
        $obligation = Obligation::query()->with(['client', 'workItem'])->findOrFail($obligationId);

        if ($obligation->workItem !== null) {
            $this->addError('workItem', 'This obligation already has a primary work item.');

            return;
        }

        $this->resetErrorBag();
        $this->selectedObligationId = $obligation->id;
        $this->selectedObligationLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->preparerMembershipId = '';
        $this->reviewerMembershipId = '';
        $this->managerMembershipId = '';
        $this->assignmentReason = '';
        $this->showAssignmentModal = true;
    }

    public function assignWork(CreateAssignedWorkItem $createAssignedWorkItem): void
    {
        $obligation = Obligation::query()->findOrFail($this->selectedObligationId);
        $createAssignedWorkItem->handle(
            $this->currentUser(),
            $obligation,
            $this->preparerMembershipId,
            $this->reviewerMembershipId,
            $this->managerMembershipId,
            $this->assignmentReason,
        );

        $this->closeAssignmentModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Primary work item assigned.');
    }

    public function closeAssignmentModal(): void
    {
        $this->reset(
            'showAssignmentModal',
            'selectedObligationId',
            'selectedObligationLabel',
            'preparerMembershipId',
            'reviewerMembershipId',
            'managerMembershipId',
            'assignmentReason',
        );
        $this->resetErrorBag();
    }

    public function openTransition(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with([
                'obligation.client',
                'assignmentHistories',
                'workflowDefinition.steps',
                'checklist.version.items',
                'checklist.completions',
            ])
            ->findOrFail($workItemId);
        Gate::authorize('transition', $workItem);

        $this->resetErrorBag();
        $this->selectedWorkItemId = $workItem->id;
        $this->selectedWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->selectedWorkItemStatus = $workItem->status->value;
        $this->targetWorkItemStatus = '';
        $this->transitionReason = '';
        $requiredItemIds = $workItem->checklist?->version->items
            ->where('required', true)
            ->pluck('id');
        $completedItemIds = $workItem->checklist?->completions
            ->pluck('checklist_item_id');
        $this->selectedRequiredChecklistTotal = $requiredItemIds?->count() ?? 0;
        $this->selectedRequiredChecklistCompleted = $requiredItemIds
            ?->intersect($completedItemIds ?? collect())
            ->count() ?? 0;
        $this->showTransitionModal = true;
    }

    public function transitionWork(TransitionWorkItem $transitionWorkItem): void
    {
        /** @var array{targetWorkItemStatus: string} $validated */
        $validated = $this->validate([
            'targetWorkItemStatus' => ['required', Rule::enum(WorkItemStatus::class)],
        ]);
        $workItem = WorkItem::query()->findOrFail($this->selectedWorkItemId);
        $transitionWorkItem->handle(
            $this->currentUser(),
            $workItem,
            WorkItemStatus::from($validated['targetWorkItemStatus']),
            $this->transitionReason,
        );

        $this->closeTransitionModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Work status updated with transition evidence.');
    }

    public function closeTransitionModal(): void
    {
        $this->reset(
            'showTransitionModal',
            'selectedWorkItemId',
            'selectedWorkItemLabel',
            'selectedWorkItemStatus',
            'targetWorkItemStatus',
            'transitionReason',
            'selectedRequiredChecklistTotal',
            'selectedRequiredChecklistCompleted',
        );
        $this->resetErrorBag();
    }

    public function openReview(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with(['obligation.client'])
            ->findOrFail($workItemId);
        Gate::authorize('review', $workItem);

        if ($workItem->status !== WorkItemStatus::UnderReview) {
            $this->addError('reviewDecision', 'This work item is no longer under review.');

            return;
        }

        $this->resetErrorBag();
        $this->reviewWorkItemId = $workItem->id;
        $this->reviewWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->reviewDecision = '';
        $this->reviewReason = '';
        $this->showReviewModal = true;
    }

    public function decideReview(DecideWorkItemReview $decideWorkItemReview): void
    {
        /** @var array{reviewDecision: string} $validated */
        $validated = $this->validate([
            'reviewDecision' => ['required', Rule::enum(ReviewDecision::class)],
        ]);
        $workItem = WorkItem::query()->findOrFail($this->reviewWorkItemId);
        $decideWorkItemReview->handle(
            $this->currentUser(),
            $workItem,
            ReviewDecision::from($validated['reviewDecision']),
            $this->reviewReason,
        );

        $this->closeReviewModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Review decision recorded with evidence.');
    }

    public function closeReviewModal(): void
    {
        $this->reset(
            'showReviewModal',
            'reviewWorkItemId',
            'reviewWorkItemLabel',
            'reviewDecision',
            'reviewReason',
        );
        $this->resetErrorBag();
    }

    public function openChecklist(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with(['obligation.client', 'checklist'])
            ->findOrFail($workItemId);
        Gate::authorize('transition', $workItem);

        if (! $workItem->checklist instanceof WorkItemChecklist) {
            $this->addError('checklistItem', 'This work item has no pinned checklist version.');

            return;
        }

        $this->resetErrorBag();
        $this->checklistWorkItemId = $workItem->id;
        $this->checklistWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->checklistItemId = '';
        $this->checklistEvidenceNote = '';
        $this->showChecklistModal = true;
    }

    public function completeChecklistItem(CompleteChecklistItem $completeChecklistItem): void
    {
        $workItem = WorkItem::query()->findOrFail($this->checklistWorkItemId);
        $item = ChecklistItem::query()->findOrFail($this->checklistItemId);
        $completeChecklistItem->handle(
            $this->currentUser(),
            $workItem,
            $item,
            $this->checklistEvidenceNote,
        );

        $this->reset('checklistItemId', 'checklistEvidenceNote');
        $this->resetErrorBag();
        unset($this->selectedChecklist, $this->obligations);
        Flux::toast(variant: 'success', text: 'Checklist evidence retained.');
    }

    public function closeChecklistModal(): void
    {
        $this->reset(
            'showChecklistModal',
            'checklistWorkItemId',
            'checklistWorkItemLabel',
            'checklistItemId',
            'checklistEvidenceNote',
        );
        $this->resetErrorBag();
        unset($this->selectedChecklist);
    }

    public function openReassignment(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with(['obligation.client', 'assignmentHistories.assignedMembership.user'])
            ->findOrFail($workItemId);
        Gate::authorize('update', $workItem);

        $this->resetErrorBag();
        $this->reassignmentWorkItemId = $workItem->id;
        $this->reassignmentWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->reassignmentRole = '';
        $this->replacementMembershipId = '';
        $this->reassignmentReason = '';
        $this->currentOwnerNames = [];

        foreach (AssignmentRole::cases() as $role) {
            $assignment = $workItem->currentAssignment($role);
            $this->currentOwnerNames[$role->value] = $assignment?->assignedMembership->user->name
                ?? 'Not assigned';
        }
        $this->showReassignmentModal = true;
    }

    public function updatedReassignmentRole(): void
    {
        $this->replacementMembershipId = '';
        unset($this->reassignmentCandidates);
    }

    public function reassignWork(ReassignWorkItem $reassignWorkItem): void
    {
        /** @var array{reassignmentRole: string} $validated */
        $validated = $this->validate([
            'reassignmentRole' => ['required', Rule::enum(AssignmentRole::class)],
        ]);
        $workItem = WorkItem::query()->findOrFail($this->reassignmentWorkItemId);
        $reassignWorkItem->handle(
            $this->currentUser(),
            $workItem,
            AssignmentRole::from($validated['reassignmentRole']),
            $this->replacementMembershipId,
            $this->reassignmentReason,
        );

        $this->closeReassignmentModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Work ownership reassigned with history retained.');
    }

    public function closeReassignmentModal(): void
    {
        $this->reset(
            'showReassignmentModal',
            'reassignmentWorkItemId',
            'reassignmentWorkItemLabel',
            'reassignmentRole',
            'replacementMembershipId',
            'reassignmentReason',
            'currentOwnerNames',
        );
        $this->resetErrorBag();
        unset($this->reassignmentCandidates);
    }

    public function openFiling(string $obligationId): void
    {
        $obligation = Obligation::query()
            ->with(['client', 'filingRecord'])
            ->findOrFail($obligationId);
        $filingRecord = $obligation->filingRecord;

        if ($filingRecord instanceof FilingRecord) {
            Gate::authorize('transition', $filingRecord);
            $this->filingRecordId = $filingRecord->id;
            $this->filingReference = $filingRecord->filing_reference ?? '';
            $this->filingFiledOn = $filingRecord->filed_on?->toDateString() ?? '';
        } else {
            Gate::authorize('create', FilingRecord::class);
            $this->filingRecordId = '';
            $this->filingReference = '';
            $this->filingFiledOn = '';
        }

        $this->resetErrorBag();
        $this->filingObligationId = $obligation->id;
        $this->filingWorkLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->filingStatus = '';
        $this->filingReason = '';
        $this->showFilingModal = true;
    }

    public function saveFiling(
        CreateFilingRecord $createFilingRecord,
        TransitionFilingRecord $transitionFilingRecord,
    ): void {
        /** @var array{filingStatus: string} $validated */
        $validated = $this->validate([
            'filingStatus' => ['required', Rule::enum(FilingStatus::class)],
        ]);
        $status = FilingStatus::from($validated['filingStatus']);

        if ($this->filingRecordId === '') {
            $obligation = Obligation::query()->findOrFail($this->filingObligationId);
            $createFilingRecord->handle($this->currentUser(), $obligation, $status, $this->filingReason);
        } else {
            $filingRecord = FilingRecord::query()->findOrFail($this->filingRecordId);
            $transitionFilingRecord->handle(
                $this->currentUser(),
                $filingRecord,
                $status,
                $this->filingReason,
                $this->filingReference === '' ? null : $this->filingReference,
                $this->filingFiledOn === '' ? null : $this->filingFiledOn,
            );
        }

        $this->closeFilingModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Filing state recorded separately from work state.');
    }

    public function closeFilingModal(): void
    {
        $this->reset(
            'showFilingModal',
            'filingObligationId',
            'filingRecordId',
            'filingWorkLabel',
            'filingStatus',
            'filingReference',
            'filingFiledOn',
            'filingReason',
        );
        $this->resetErrorBag();
        unset($this->filingStatusOptions);
    }

    public function openPayment(string $obligationId): void
    {
        $obligation = Obligation::query()
            ->with(['client', 'paymentRecord'])
            ->findOrFail($obligationId);
        $paymentRecord = $obligation->paymentRecord;

        if ($paymentRecord instanceof PaymentRecord) {
            Gate::authorize('transition', $paymentRecord);
            $this->paymentRecordId = $paymentRecord->id;
            $this->paymentReference = $paymentRecord->payment_reference ?? '';
            $this->paymentPaidOn = $paymentRecord->paid_on?->toDateString() ?? '';
        } else {
            Gate::authorize('create', PaymentRecord::class);
            $this->paymentRecordId = '';
            $this->paymentReference = '';
            $this->paymentPaidOn = '';
        }

        $this->resetErrorBag();
        $this->paymentObligationId = $obligation->id;
        $this->paymentWorkLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->paymentStatus = '';
        $this->paymentReason = '';
        $this->showPaymentModal = true;
    }

    public function savePayment(
        CreatePaymentRecord $createPaymentRecord,
        TransitionPaymentRecord $transitionPaymentRecord,
    ): void {
        /** @var array{paymentStatus: string} $validated */
        $validated = $this->validate([
            'paymentStatus' => ['required', Rule::enum(PaymentStatus::class)],
        ]);
        $status = PaymentStatus::from($validated['paymentStatus']);

        if ($this->paymentRecordId === '') {
            $obligation = Obligation::query()->findOrFail($this->paymentObligationId);
            $createPaymentRecord->handle($this->currentUser(), $obligation, $status, $this->paymentReason);
        } else {
            $paymentRecord = PaymentRecord::query()->findOrFail($this->paymentRecordId);
            $transitionPaymentRecord->handle(
                $this->currentUser(),
                $paymentRecord,
                $status,
                $this->paymentReason,
                $this->paymentReference === '' ? null : $this->paymentReference,
                $this->paymentPaidOn === '' ? null : $this->paymentPaidOn,
            );
        }

        $this->closePaymentModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Payment state recorded separately from work and filing state.');
    }

    public function closePaymentModal(): void
    {
        $this->reset(
            'showPaymentModal',
            'paymentObligationId',
            'paymentRecordId',
            'paymentWorkLabel',
            'paymentStatus',
            'paymentReference',
            'paymentPaidOn',
            'paymentReason',
        );
        $this->resetErrorBag();
        unset($this->paymentStatusOptions);
    }

    public function openReopen(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with('obligation.client')
            ->findOrFail($workItemId);
        Gate::authorize('create', WorkItem::class);

        if ($workItem->status !== WorkItemStatus::Completed || $workItem->isFollowUp()) {
            $this->addError('reopen', 'Only completed original work can be reopened as a follow-up.');

            return;
        }

        $this->resetErrorBag();
        $this->reopenWorkItemId = $workItem->id;
        $this->reopenWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->reopenReason = '';
        $this->showReopenModal = true;
    }

    public function reopenWork(ReopenWorkItem $reopenWorkItem): void
    {
        $workItem = WorkItem::query()->findOrFail($this->reopenWorkItemId);
        $reopenWorkItem->handle($this->currentUser(), $workItem, $this->reopenReason);

        $this->closeReopenModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Follow-up work created. The completed original is unchanged.');
    }

    public function closeReopenModal(): void
    {
        $this->reset('showReopenModal', 'reopenWorkItemId', 'reopenWorkItemLabel', 'reopenReason');
        $this->resetErrorBag();
    }

    public function openRisk(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with('obligation.client')
            ->findOrFail($workItemId);
        Gate::authorize('update', $workItem);

        $this->resetErrorBag();
        $this->riskWorkItemId = $workItem->id;
        $this->riskWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->riskLevel = $workItem->risk_status->value;
        $this->riskReason = '';
        $this->showRiskModal = true;
    }

    public function saveRisk(SetWorkItemRiskStatus $setWorkItemRiskStatus): void
    {
        /** @var array{riskLevel: string} $validated */
        $validated = $this->validate([
            'riskLevel' => ['required', Rule::enum(RiskLevel::class)],
        ]);
        $workItem = WorkItem::query()->findOrFail($this->riskWorkItemId);
        $setWorkItemRiskStatus->handle(
            $this->currentUser(),
            $workItem,
            RiskLevel::from($validated['riskLevel']),
            $this->riskReason,
        );

        $this->closeRiskModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Risk status recorded with a retained reason.');
    }

    public function closeRiskModal(): void
    {
        $this->reset('showRiskModal', 'riskWorkItemId', 'riskWorkItemLabel', 'riskLevel', 'riskReason');
        $this->resetErrorBag();
    }

    public function openTax(string $obligationId): void
    {
        $obligation = Obligation::query()
            ->with(['client', 'taxRecord'])
            ->findOrFail($obligationId);
        $taxRecord = $obligation->taxRecord;

        if ($taxRecord instanceof TaxRecord) {
            Gate::authorize('amend', $taxRecord);
            $this->taxRecordId = $taxRecord->id;
            $this->taxType = $taxRecord->tax_type->value;
            $this->taxPeriodLabel = $taxRecord->period_label;
            $this->taxCurrency = $taxRecord->currency;
            $this->taxTaxableAmount = $taxRecord->taxable_amount;
            $this->taxTaxAmount = $taxRecord->tax_amount;
            $this->taxTargetStatus = $taxRecord->status->value;
        } else {
            Gate::authorize('create', TaxRecord::class);
            $this->taxRecordId = '';
            $this->taxType = TaxType::Vat->value;
            $this->taxPeriodLabel = '';
            $this->taxCurrency = 'AED';
            $this->taxTaxableAmount = '';
            $this->taxTaxAmount = '';
            $this->taxTargetStatus = TaxRecordStatus::Draft->value;
        }

        $this->resetErrorBag();
        $this->taxObligationId = $obligation->id;
        $this->taxWorkLabel = "{$obligation->client->internal_code} · {$obligation->obligation_type}";
        $this->taxReason = '';
        $this->showTaxModal = true;
    }

    public function saveTax(
        CreateTaxRecord $createTaxRecord,
        AmendTaxRecord $amendTaxRecord,
    ): void {
        $this->validate([
            'taxType' => ['required', Rule::enum(TaxType::class)],
            'taxTargetStatus' => ['required', Rule::enum(TaxRecordStatus::class)],
            'taxPeriodLabel' => ['required', 'string', 'max:100'],
            'taxCurrency' => ['required', 'string', 'size:3', 'alpha'],
            'taxTaxableAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'taxTaxAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ]);

        if ($this->taxRecordId === '') {
            $obligation = Obligation::query()->findOrFail($this->taxObligationId);
            $createTaxRecord->handle(
                $this->currentUser(),
                $obligation,
                TaxType::from($this->taxType),
                $this->taxPeriodLabel,
                $this->taxCurrency,
                $this->taxTaxableAmount,
                $this->taxTaxAmount,
                $this->taxReason,
            );
        } else {
            $taxRecord = TaxRecord::query()->findOrFail($this->taxRecordId);
            $amendTaxRecord->handle(
                $this->currentUser(),
                $taxRecord,
                $this->taxTaxableAmount,
                $this->taxTaxAmount,
                TaxRecordStatus::from($this->taxTargetStatus),
                $this->taxReason,
            );
        }

        $this->closeTaxModal();
        unset($this->obligations);
        Flux::toast(variant: 'success', text: 'Tax figures recorded separately from work, filing and payment state.');
    }

    public function closeTaxModal(): void
    {
        $this->reset(
            'showTaxModal',
            'taxObligationId',
            'taxRecordId',
            'taxWorkLabel',
            'taxType',
            'taxPeriodLabel',
            'taxCurrency',
            'taxTaxableAmount',
            'taxTaxAmount',
            'taxTargetStatus',
            'taxReason',
        );
        $this->resetErrorBag();
    }

    public function openMigration(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with(['obligation.client', 'workflowDefinition'])
            ->findOrFail($workItemId);
        Gate::authorize('update', $workItem);

        if (in_array($workItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
            $this->addError('migrationTargetDefinitionId', 'Completed or cancelled work cannot be migrated to another workflow version.');

            return;
        }

        $this->resetErrorBag();
        $this->migrationWorkItemId = $workItem->id;
        $this->migrationWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->migrationCurrentVersion = $workItem->workflowDefinition->version;
        $this->migrationTargetDefinitionId = '';
        $this->migrationReason = '';
        $this->showMigrationModal = true;
    }

    public function migrateWorkflowVersion(MigrateWorkItemWorkflowVersion $migrateWorkItemWorkflowVersion): void
    {
        /** @var array{migrationTargetDefinitionId: string} $validated */
        $validated = $this->validate([
            'migrationTargetDefinitionId' => ['required', 'string', 'ulid'],
        ]);
        $workItem = WorkItem::query()->findOrFail($this->migrationWorkItemId);
        $migrateWorkItemWorkflowVersion->handle(
            $this->currentUser(),
            $workItem,
            $validated['migrationTargetDefinitionId'],
            $this->migrationReason,
        );

        $this->closeMigrationModal();
        unset($this->obligations, $this->availableWorkflowVersions);
        Flux::toast(variant: 'success', text: 'Work migrated to a later workflow version with audit evidence.');
    }

    public function closeMigrationModal(): void
    {
        $this->reset(
            'showMigrationModal',
            'migrationWorkItemId',
            'migrationWorkItemLabel',
            'migrationCurrentVersion',
            'migrationTargetDefinitionId',
            'migrationReason',
        );
        $this->resetErrorBag();
        unset($this->availableWorkflowVersions);
    }

    /**
     * @return LengthAwarePaginator<int, Obligation>
     */
    #[Computed]
    public function obligations(): LengthAwarePaginator
    {
        $search = trim($this->search);

        $membership = app(FirmContext::class)->membership();
        $canManageObligations = $membership?->hasPermission(Permission::ManageObligations) ?? false;

        return Obligation::query()
            ->with([
                'client',
                'filingRecord',
                'paymentRecord',
                'taxRecord',
                'workItem.followUps',
                'workItem.assignmentHistories.assignedMembership.user',
                'workItem.workflowDefinition.steps',
                'workItem.checklist.version.items',
                'workItem.checklist.completions',
            ])
            ->when(
                ! $canManageObligations,
                static function (Builder $query) use ($membership): void {
                    $query->whereHas(
                        'workItem.assignmentHistories',
                        static fn (Builder $query): Builder => $query
                            ->where('assigned_membership_id', $membership?->id)
                            ->whereRaw(
                                'assignment_histories.id = (
                                    select max(latest_assignment.id)
                                    from assignment_histories as latest_assignment
                                    where latest_assignment.work_item_id = assignment_histories.work_item_id
                                    and latest_assignment.assignment_role = assignment_histories.assignment_role
                                )',
                            ),
                    );
                },
            )
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(static function (Builder $query) use ($search): void {
                        $query
                            ->where('obligation_type', 'like', "%{$search}%")
                            ->orWhere('period_label', 'like', "%{$search}%")
                            ->orWhereHas('client', static function (Builder $query) use ($search): void {
                                $query
                                    ->where('internal_code', 'like', "%{$search}%")
                                    ->orWhere('legal_name', 'like', "%{$search}%");
                            });
                    });
                },
            )
            ->orderByRaw('coalesce(effective_due_date, statutory_due_date)')
            ->orderBy('id')
            ->paginate(25);
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()
            ->where('status', ClientStatus::Active)
            ->orderBy('legal_name')
            ->get();
    }

    /** @return Collection<int, Obligation> */
    #[Computed]
    public function replacementObligations(): Collection
    {
        return Obligation::query()
            ->with('client')
            ->where('status', ObligationStatus::Open)
            ->when(
                $this->dispositionObligationId !== '',
                fn (Builder $query): Builder => $query->whereKeyNot($this->dispositionObligationId),
            )
            ->orderByRaw('coalesce(effective_due_date, statutory_due_date)')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function preparers(): Collection
    {
        return $this->eligibleMembers(Permission::PrepareWork);
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function reviewers(): Collection
    {
        return $this->eligibleMembers(Permission::ReviewWork);
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function managers(): Collection
    {
        return $this->eligibleMembers(Permission::AssignWork);
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function reassignmentCandidates(): Collection
    {
        $role = AssignmentRole::tryFrom($this->reassignmentRole);
        $permission = match ($role) {
            AssignmentRole::Preparer => Permission::PrepareWork,
            AssignmentRole::Reviewer => Permission::ReviewWork,
            AssignmentRole::ResponsibleManager => Permission::AssignWork,
            null => null,
        };

        return $permission instanceof Permission
            ? $this->eligibleMembers($permission)
            : new Collection;
    }

    /**
     * Opening states when no filing exists yet, otherwise the edges the current filing state allows.
     *
     * @return list<FilingStatus>
     */
    #[Computed]
    public function filingStatusOptions(): array
    {
        if ($this->filingRecordId === '') {
            return [FilingStatus::NotFiled, FilingStatus::NotRequired];
        }

        $filingRecord = FilingRecord::query()->find($this->filingRecordId);

        return $filingRecord instanceof FilingRecord ? $filingRecord->allowedTransitions() : [];
    }

    /**
     * Opening states when no payment exists yet, otherwise the edges the current payment state allows.
     *
     * @return list<PaymentStatus>
     */
    #[Computed]
    public function paymentStatusOptions(): array
    {
        if ($this->paymentRecordId === '') {
            return [PaymentStatus::Pending, PaymentStatus::Unknown, PaymentStatus::NotRequired];
        }

        $paymentRecord = PaymentRecord::query()->find($this->paymentRecordId);

        return $paymentRecord instanceof PaymentRecord ? $paymentRecord->allowedTransitions() : [];
    }

    /** @return Collection<int, WorkflowDefinition> */
    #[Computed]
    public function availableWorkflowVersions(): Collection
    {
        if ($this->migrationWorkItemId === '') {
            return new Collection;
        }

        $workItem = WorkItem::query()->with('workflowDefinition')->find($this->migrationWorkItemId);

        if (! $workItem instanceof WorkItem) {
            return new Collection;
        }

        return WorkflowDefinition::query()
            ->where('definition_key', $workItem->workflowDefinition->definition_key)
            ->where('status', 'published')
            ->where('version', '>', $workItem->workflowDefinition->version)
            ->orderBy('version')
            ->get();
    }

    #[Computed]
    public function currentFirmName(): string
    {
        return app(FirmContext::class)->firm()->name;
    }

    /** @return list<WorkItemStatus> */
    #[Computed]
    public function transitionOptions(): array
    {
        if ($this->selectedWorkItemId === '') {
            return [];
        }

        $workItem = WorkItem::query()
            ->with(['assignmentHistories', 'workflowDefinition.steps'])
            ->findOrFail($this->selectedWorkItemId);
        $membershipId = app(FirmContext::class)->membership()?->id;

        return $workItem->genericTransitionTargetsFor($membershipId);
    }

    #[Computed]
    public function selectedChecklist(): ?WorkItemChecklist
    {
        if ($this->checklistWorkItemId === '') {
            return null;
        }

        return WorkItemChecklist::query()
            ->with(['workItem.assignmentHistories', 'version.template', 'version.items', 'completions.item'])
            ->where('work_item_id', $this->checklistWorkItemId)
            ->firstOrFail();
    }

    #[Computed]
    public function canCompleteChecklist(): bool
    {
        $checklist = $this->selectedChecklist();
        $membershipId = app(FirmContext::class)->membership()?->id;

        return $checklist?->workItem
            ->currentAssignment(AssignmentRole::Preparer)
            ?->assigned_membership_id === $membershipId;
    }

    public function render(): View
    {
        return view('livewire.obligations.index');
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return Collection<int, FirmMembership> */
    private function eligibleMembers(Permission $permission): Collection
    {
        return FirmMembership::query()
            ->with('user')
            ->where('status', FirmMembershipStatus::Active)
            ->get()
            ->filter(static fn (FirmMembership $membership): bool => $membership->hasPermission($permission))
            ->sortBy(static fn (FirmMembership $membership): string => $membership->user->name)
            ->values();
    }
}
