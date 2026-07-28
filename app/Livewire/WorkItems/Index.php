<?php

declare(strict_types=1);

namespace App\Livewire\WorkItems;

use App\Actions\Documents\StoreDocumentEvidence;
use App\Actions\Operations\DeleteOperationalFilter;
use App\Actions\Operations\SaveOperationalFilter;
use App\Enums\DocumentPurpose;
use App\Enums\Feature;
use App\Enums\OperationalFilterSurface;
use App\Enums\Permission;
use App\Enums\WorkItemStatus;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Work register')]
final class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $savedFilterName = '';

    public string $selectedSavedFilterId = '';

    public bool $showEvidenceModal = false;

    public ?string $evidenceWorkItemId = null;

    public string $evidenceWorkItemLabel = '';

    public string $evidencePurpose = DocumentPurpose::SourceDocument->value;

    public ?TemporaryUploadedFile $documentUpload = null;

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::ComplianceOperations, $firmContext->firmId()),
            404,
        );

        Gate::authorize('viewAny', WorkItem::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
        unset($this->workGroups);
    }

    public function saveFilter(SaveOperationalFilter $action): void
    {
        $saved = $action->handle(
            $this->currentUser(),
            OperationalFilterSurface::WorkRegister,
            $this->savedFilterName,
            ['search' => $this->search, 'status' => $this->status],
        );
        $this->selectedSavedFilterId = $saved->id;
        $this->reset('savedFilterName');
        unset($this->savedFilters);
    }

    public function applySavedFilter(): void
    {
        $filter = SavedOperationalFilter::query()
            ->where('user_id', $this->currentUser()->id)
            ->where('surface', OperationalFilterSurface::WorkRegister)
            ->findOrFail($this->selectedSavedFilterId);
        Gate::authorize('view', $filter);
        $this->search = (string) ($filter->filters['search'] ?? '');
        $this->status = (string) ($filter->filters['status'] ?? '');
        $this->resetPage();
        unset($this->workGroups);
    }

    public function deleteSavedFilter(DeleteOperationalFilter $action): void
    {
        $filter = SavedOperationalFilter::query()->findOrFail($this->selectedSavedFilterId);
        $action->handle($this->currentUser(), $filter);
        $this->reset('selectedSavedFilterId');
        unset($this->savedFilters);
    }

    /**
     * @return LengthAwarePaginator<int, WorkItem>
     */
    #[Computed]
    public function workGroups(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $membership = app(FirmContext::class)->membership();
        $canManageAll = $membership?->hasPermission(Permission::AssignWork) ?? false;
        $membershipId = $membership?->id;
        $status = $this->status;

        return WorkItem::query()
            ->whereNull('parent_work_item_id')
            ->with([
                'obligation.client',
                'workflowDefinition',
                'assignmentHistories.assignedMembership.user',
                'documentEvidence.scanEvents',
                'documentEvidence.uploader',
                'followUps' => static fn ($query) => $query
                    ->with([
                        'workflowDefinition',
                        'assignmentHistories.assignedMembership.user',
                        'documentEvidence.scanEvents',
                        'documentEvidence.uploader',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->when(
                ! $canManageAll,
                static fn (Builder $query): Builder => $query->where(
                    static fn (Builder $query): Builder => $query
                        ->whereHas(
                            'assignmentHistories',
                            static fn (Builder $query): Builder => $query
                                ->where('assigned_membership_id', $membershipId),
                        )
                        ->orWhereHas(
                            'followUps.assignmentHistories',
                            static fn (Builder $query): Builder => $query
                                ->where('assigned_membership_id', $membershipId),
                        ),
                ),
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder => $query->where(
                    static fn (Builder $query): Builder => $query
                        ->where('status', $status)
                        ->orWhereHas(
                            'followUps',
                            static fn (Builder $query): Builder => $query->where('status', $status),
                        ),
                ),
            )
            ->when(
                $search !== '',
                static fn (Builder $query): Builder => $query->whereHas(
                    'obligation',
                    static fn (Builder $query): Builder => $query
                        ->where('obligation_type', 'like', "%{$search}%")
                        ->orWhere('period_label', 'like', "%{$search}%")
                        ->orWhereHas(
                            'client',
                            static fn (Builder $query): Builder => $query
                                ->where('internal_code', 'like', "%{$search}%")
                                ->orWhere('legal_name', 'like', "%{$search}%"),
                        ),
                ),
            )
            ->orderBy(
                WorkItem::query()
                    ->selectRaw('coalesce(effective_due_date, statutory_due_date)')
                    ->from('obligations')
                    ->whereColumn('obligations.id', 'work_items.obligation_id')
                    ->limit(1),
            )
            ->orderBy('id')
            ->paginate(20);
    }

    public function openEvidence(string $workItemId): void
    {
        $workItem = WorkItem::query()
            ->with('obligation.client')
            ->findOrFail($workItemId);
        $workItem->loadMissing('assignmentHistories');
        Gate::authorize('evidence', $workItem);

        if (in_array($workItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
            $this->addError('documentUpload', 'Evidence can be added only while work is open.');

            return;
        }

        $this->resetEvidenceForm();
        $this->evidenceWorkItemId = $workItem->id;
        $this->evidenceWorkItemLabel = "{$workItem->obligation->client->internal_code} · {$workItem->obligation->obligation_type}";
        $this->showEvidenceModal = true;
    }

    public function saveEvidence(StoreDocumentEvidence $storeDocumentEvidence): void
    {
        $this->validate([
            'evidenceWorkItemId' => ['required', 'string'],
            'evidencePurpose' => ['required', Rule::enum(DocumentPurpose::class)],
            'documentUpload' => ['required', 'file'],
        ]);

        $workItem = WorkItem::query()->findOrFail($this->evidenceWorkItemId);
        $purpose = DocumentPurpose::from($this->evidencePurpose);

        $storeDocumentEvidence->handle(
            $this->currentUser(),
            $workItem,
            $purpose,
            $this->documentUpload,
        );

        $this->showEvidenceModal = false;
        $this->resetEvidenceForm();
        unset($this->workGroups);
    }

    public function closeEvidence(): void
    {
        $this->showEvidenceModal = false;
        $this->resetEvidenceForm();
    }

    /** @return list<DocumentPurpose> */
    #[Computed]
    public function evidencePurposes(): array
    {
        return DocumentPurpose::cases();
    }

    /** @return list<WorkItemStatus> */
    #[Computed]
    public function statuses(): array
    {
        return WorkItemStatus::cases();
    }

    /** @return Collection<int, SavedOperationalFilter> */
    #[Computed]
    public function savedFilters(): Collection
    {
        return SavedOperationalFilter::query()
            ->where('user_id', $this->currentUser()->id)
            ->where('surface', OperationalFilterSurface::WorkRegister)
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.work-items.index');
    }

    private function resetEvidenceForm(): void
    {
        $this->reset(
            'evidenceWorkItemId',
            'evidenceWorkItemLabel',
            'documentUpload',
        );
        $this->evidencePurpose = DocumentPurpose::SourceDocument->value;
        $this->resetErrorBag();
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        return $user;
    }
}
