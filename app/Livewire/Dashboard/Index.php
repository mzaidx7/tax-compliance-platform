<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Actions\Clients\ApproveClientReminder;
use App\Actions\Operations\DeleteOperationalFilter;
use App\Actions\Operations\SaveOperationalFilter;
use App\Enums\AssignmentRole;
use App\Enums\ClientReminderStatus;
use App\Enums\ClientStatus;
use App\Enums\FirmMembershipStatus;
use App\Enums\ObligationStatus;
use App\Enums\OperationalFilterSurface;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientReminderRequest;
use App\Models\FirmMembership;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Dashboard')]
final class Index extends Component
{
    use WithPagination;

    public string $clientId = '';

    public string $selectedMonth = '';

    public string $category = 'all';

    public string $clientSearch = '';

    public string $teamMembershipId = '';

    public string $attentionStatus = 'all';

    public int $horizonDays = 30;

    public string $savedFilterName = '';

    public string $selectedSavedFilterId = '';

    public bool $showTutorialPrompt = false;

    public function mount(): void
    {
        $this->selectedMonth = today()->format('Y-m');
        $user = $this->currentUser();
        $this->showTutorialPrompt = $user->tutorial_prompt_dismissed_at === null
            && $user->tutorial_completed_at === null;
    }

    public function dismissTutorialPrompt(): void
    {
        $this->currentUser()->forceFill(['tutorial_prompt_dismissed_at' => now()])->save();
        $this->showTutorialPrompt = false;
    }

    public function updatedSelectedMonth(): void
    {
        $this->validate(['selectedMonth' => ['required', 'date_format:Y-m']]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedCategory(): void
    {
        $this->validate(['category' => ['required', Rule::in(['all', 'vat', 'corporate-tax', 'documents'])]]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedClientSearch(): void
    {
        $this->validate(['clientSearch' => ['nullable', 'string', 'max:100']]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedTeamMembershipId(): void
    {
        $this->validate(['teamMembershipId' => ['nullable', 'string', 'max:26']]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedAttentionStatus(): void
    {
        $this->validate(['attentionStatus' => ['required', Rule::in(['all', 'upcoming', 'overdue'])]]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedHorizonDays(): void
    {
        $this->validate(['horizonDays' => ['required', 'integer', Rule::in([7, 14, 30, 60, 90])]]);
        $this->flushOperationalData();
        $this->resetPage('priorityPage');
    }

    public function updatedClientId(): void
    {
        $this->validate(['clientId' => ['nullable', 'string', 'max:26']]);
        $this->flushOperationalData();
    }

    public function saveFilter(SaveOperationalFilter $action): void
    {
        $saved = $action->handle(
            $this->currentUser(),
            OperationalFilterSurface::Dashboard,
            $this->savedFilterName,
            [
                'client_id' => $this->clientId,
                'horizon_days' => $this->horizonDays,
                'selected_month' => $this->selectedMonth,
                'category' => $this->category,
                'client_search' => $this->clientSearch,
                'team_membership_id' => $this->teamMembershipId,
                'attention_status' => $this->attentionStatus,
            ],
        );
        $this->selectedSavedFilterId = $saved->id;
        $this->reset('savedFilterName');
        unset($this->savedFilters);
    }

    public function applySavedFilter(): void
    {
        $filter = SavedOperationalFilter::query()
            ->where('user_id', $this->currentUser()->id)
            ->where('surface', OperationalFilterSurface::Dashboard)
            ->findOrFail($this->selectedSavedFilterId);
        Gate::authorize('view', $filter);
        $this->clientId = (string) ($filter->filters['client_id'] ?? '');
        $this->horizonDays = (int) ($filter->filters['horizon_days'] ?? 30);
        $this->selectedMonth = (string) ($filter->filters['selected_month'] ?? today()->format('Y-m'));
        $this->category = (string) ($filter->filters['category'] ?? 'all');
        $this->clientSearch = (string) ($filter->filters['client_search'] ?? '');
        $this->teamMembershipId = (string) ($filter->filters['team_membership_id'] ?? '');
        $this->attentionStatus = (string) ($filter->filters['attention_status'] ?? 'all');
        $this->flushOperationalData();
    }

    public function deleteSavedFilter(DeleteOperationalFilter $action): void
    {
        $filter = SavedOperationalFilter::query()->findOrFail($this->selectedSavedFilterId);
        $action->handle($this->currentUser(), $filter);
        $this->reset('selectedSavedFilterId');
        unset($this->savedFilters);
    }

    /** @return EloquentCollection<int, Client> */
    #[Computed]
    public function clients(): EloquentCollection
    {
        $query = Client::query()->where('status', ClientStatus::Active);
        $membership = app(FirmContext::class)->membership();
        if (
            $membership !== null
            && ! $membership->hasPermission(Permission::ManageObligations)
            && ! $membership->hasPermission(Permission::AssignWork)
            && ! $membership->hasPermission(Permission::ViewReports)
        ) {
            $query->whereHas(
                'obligations.workItems.assignmentHistories',
                static fn (Builder $query): Builder => $query->where('assigned_membership_id', $membership->id),
            );
        }

        return $query
            ->when(trim($this->clientSearch) !== '', function (Builder $query): void {
                $search = '%'.trim($this->clientSearch).'%';
                $query->where(static fn (Builder $scope): Builder => $scope
                    ->where('internal_code', 'like', $search)
                    ->orWhere('legal_name', 'like', $search));
            })
            ->orderBy('legal_name')
            ->get();
    }

    /** @return EloquentCollection<int, SavedOperationalFilter> */
    #[Computed]
    public function savedFilters(): EloquentCollection
    {
        return SavedOperationalFilter::query()
            ->where('user_id', $this->currentUser()->id)
            ->where('surface', OperationalFilterSurface::Dashboard)
            ->orderBy('name')
            ->get();
    }

    /** @return EloquentCollection<int, FirmMembership> */
    #[Computed]
    public function teamMembers(): EloquentCollection
    {
        return FirmMembership::query()
            ->with('user')
            ->where('status', FirmMembershipStatus::Active)
            ->orderBy('role')
            ->get()
            ->sortBy('user.name')
            ->values();
    }

    /**
     * @return array{
     *  due_soon: int, overdue: int, high_risk: int, overdue_payments: int,
     *  awaiting_client: int, under_review: int, unassigned: int, active_workload: int,
     *  reminders_awaiting_review: int, vat_due: int, corporate_tax_due: int,
     *  documents_expiring: int, missing_information: int
     * }
     */
    #[Computed]
    public function summary(): array
    {
        $obligations = $this->filterObligations($this->visibleObligations())
            ->where('status', ObligationStatus::Open);
        $workItems = $this->filterWorkItems($this->visibleWorkItems())
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled]);
        $payments = $this->filterPayments($this->visiblePayments());
        [$monthStart, $monthEnd] = $this->monthRange();
        $monthObligations = $this->filterObligations($this->visibleObligations())
            ->where('status', ObligationStatus::Open)
            ->whereRaw(
                'coalesce(effective_due_date, statutory_due_date) between ? and ?',
                [$monthStart->toDateString(), $monthEnd->toDateString()],
            );
        $visibleClientIds = $this->clients()->pluck('id');
        $documents = ClientDocument::query()
            ->whereIn('client_id', $visibleClientIds)
            ->whereNotNull('expires_on')
            ->whereDoesntHave('successor')
            ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId));

        return [
            'due_soon' => (clone $obligations)
                ->whereRaw(
                    'coalesce(effective_due_date, statutory_due_date) between ? and ?',
                    [today()->toDateString(), today()->addDays($this->horizonDays)->toDateString()],
                )
                ->count(),
            'overdue' => (clone $obligations)
                ->whereRaw('coalesce(effective_due_date, statutory_due_date) < ?', [today()->toDateString()])
                ->count(),
            'high_risk' => (clone $workItems)
                ->where('risk_status', RiskLevel::High)
                ->count(),
            'overdue_payments' => (clone $payments)
                ->where('status', PaymentStatus::Overdue)
                ->count(),
            'awaiting_client' => (clone $workItems)
                ->whereIn('status', [WorkItemStatus::AwaitingClient, WorkItemStatus::AwaitingClientApproval])
                ->count(),
            'under_review' => (clone $workItems)
                ->where('status', WorkItemStatus::UnderReview)
                ->count(),
            'unassigned' => (clone $workItems)
                ->whereDoesntHave('assignmentHistories')
                ->count(),
            'active_workload' => (clone $workItems)->count(),
            'reminders_awaiting_review' => ClientReminderRequest::query()
                ->where('status', ClientReminderStatus::AwaitingReview)
                ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId))
                ->count(),
            'vat_due' => (clone $monthObligations)->where('obligation_type', 'VAT Return')->count(),
            'corporate_tax_due' => (clone $monthObligations)->where('obligation_type', 'Corporate Tax Return')->count(),
            'documents_expiring' => (clone $documents)
                ->whereBetween('expires_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'missing_information' => Client::query()
                ->whereIn('id', $visibleClientIds)
                ->when($this->clientId !== '', fn (Builder $query): Builder => $query->whereKey($this->clientId))
                ->where(static fn (Builder $query): Builder => $query
                    ->whereNull('primary_email')
                    ->orWhere(static fn (Builder $vat): Builder => $vat
                        ->whereNotNull('vat_trn')
                        ->whereNull('vat_period_ends_on'))
                    ->orWhere(static fn (Builder $ct): Builder => $ct
                        ->whereNotNull('corporate_tax_trn')
                        ->whereNull('corporate_tax_period_ends_on')))
                ->count(),
        ];
    }

    /** @return EloquentCollection<int, ClientReminderRequest> */
    #[Computed]
    public function remindersAwaitingReview(): EloquentCollection
    {
        return ClientReminderRequest::query()
            ->with(['client', 'source'])
            ->where('status', ClientReminderStatus::AwaitingReview)
            ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId))
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    /**
     * @return list<array{month: string, label: string, vat: int, corporate_tax: int, documents: int, total: int}>
     */
    #[Computed]
    public function workloadStrip(): array
    {
        [$start] = $this->monthRange();
        $end = $start->copy()->addMonthsNoOverflow(11)->endOfMonth();
        $obligations = $this->filterObligations($this->visibleObligations())
            ->where('status', ObligationStatus::Open)
            ->whereRaw(
                'coalesce(effective_due_date, statutory_due_date) between ? and ?',
                [$start->toDateString(), $end->toDateString()],
            )
            ->get();
        $documents = ClientDocument::query()
            ->whereIn('client_id', $this->clients()->pluck('id'))
            ->whereNotNull('expires_on')
            ->whereDoesntHave('successor')
            ->whereBetween('expires_on', [$start->toDateString(), $end->toDateString()])
            ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId))
            ->get();
        $rows = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->copy()->addMonthsNoOverflow($offset);
            $key = $month->format('Y-m');
            $vat = $obligations->filter(static fn (Obligation $obligation): bool => $obligation->obligation_type === 'VAT Return'
                && $obligation->effectiveDueDate()->format('Y-m') === $key)->count();
            $corporateTax = $obligations->filter(static fn (Obligation $obligation): bool => $obligation->obligation_type === 'Corporate Tax Return'
                && $obligation->effectiveDueDate()->format('Y-m') === $key)->count();
            $documentCount = $documents->filter(static fn (ClientDocument $document): bool => $document->expires_on->format('Y-m') === $key)->count();
            $rows[] = [
                'month' => $key,
                'label' => $month->format('M y'),
                'vat' => $vat,
                'corporate_tax' => $corporateTax,
                'documents' => $documentCount,
                'total' => $vat + $corporateTax + $documentCount,
            ];
        }

        return $rows;
    }

    /** @return LengthAwarePaginator<array-key, object> */
    #[Computed]
    public function portfolioItems(): LengthAwarePaginator
    {
        [$monthStart, $monthEnd] = $this->monthRange();
        $search = '%'.trim($this->clientSearch).'%';
        $obligations = $this->filterObligations($this->visibleObligations())
            ->join('clients', 'clients.id', '=', 'obligations.client_id')
            ->where('obligations.status', ObligationStatus::Open)
            ->when($this->teamMembershipId !== '', fn (Builder $query): Builder => $query->whereHas(
                'workItems.assignmentHistories',
                fn (Builder $assignment): Builder => $assignment->where('assigned_membership_id', $this->teamMembershipId),
            ))
            ->whereRaw('coalesce(obligations.effective_due_date, obligations.statutory_due_date) <= ?', [$monthEnd->toDateString()])
            ->when(trim($this->clientSearch) !== '', static fn (Builder $query): Builder => $query
                ->where(static fn (Builder $scope): Builder => $scope
                    ->where('clients.internal_code', 'like', $search)
                    ->orWhere('clients.legal_name', 'like', $search)))
            ->select([
                DB::raw("'obligation' as item_kind"),
                'obligations.id',
                'obligations.client_id',
                'clients.internal_code as client_code',
                'clients.legal_name as client_name',
                'obligations.obligation_type as title',
                'obligations.period_label as detail',
                DB::raw('coalesce(obligations.effective_due_date, obligations.statutory_due_date) as event_date'),
                'obligations.status',
            ]);

        $documents = ClientDocument::query()
            ->join('clients', 'clients.id', '=', 'client_documents.client_id')
            ->join('document_type_versions', 'document_type_versions.id', '=', 'client_documents.document_type_version_id')
            ->leftJoin('client_people', 'client_people.id', '=', 'client_documents.client_person_id')
            ->whereIn('client_documents.client_id', $this->clients()->pluck('id'))
            ->whereNotNull('client_documents.expires_on')
            ->whereDoesntHave('successor')
            ->whereDate('client_documents.expires_on', '<=', $monthEnd->toDateString())
            ->when($this->teamMembershipId !== '', fn (Builder $query): Builder => $query->whereHas(
                'client.serviceEnrollments',
                fn (Builder $service): Builder => $service->where('responsible_membership_id', $this->teamMembershipId),
            ))
            ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_documents.client_id', $this->clientId))
            ->when(trim($this->clientSearch) !== '', static fn (Builder $query): Builder => $query
                ->where(static fn (Builder $scope): Builder => $scope
                    ->where('clients.internal_code', 'like', $search)
                    ->orWhere('clients.legal_name', 'like', $search)))
            ->when(in_array($this->category, ['vat', 'corporate-tax'], true), fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->select([
                DB::raw("'document' as item_kind"),
                'client_documents.id',
                'client_documents.client_id',
                'clients.internal_code as client_code',
                'clients.legal_name as client_name',
                'document_type_versions.name as title',
                DB::raw("coalesce(client_people.name, 'Client document') as detail"),
                'client_documents.expires_on as event_date',
            ])
            ->selectRaw(
                "case when client_documents.expires_on < ? then 'expired' else 'current' end as status",
                [today()->toDateString()],
            );

        $union = $obligations->toBase()->unionAll($documents->toBase());

        return DB::query()
            ->fromSub($union, 'portfolio_items')
            ->where(function ($query) use ($monthStart): void {
                $query->whereDate('event_date', '>=', $monthStart->toDateString())
                    ->orWhereDate('event_date', '<', today()->toDateString());
            })
            ->when($this->attentionStatus === 'overdue', fn ($query) => $query->whereDate('event_date', '<', today()->toDateString()))
            ->when($this->attentionStatus === 'upcoming', fn ($query) => $query->whereDate('event_date', '>=', today()->toDateString()))
            ->orderBy('event_date')
            ->orderBy('client_code')
            ->paginate(20, ['*'], 'priorityPage');
    }

    public function approveReminder(string $requestId, ApproveClientReminder $action): void
    {
        $request = ClientReminderRequest::query()->findOrFail($requestId);
        $action->handle($this->currentUser(), $request);
        unset($this->summary, $this->remindersAwaitingReview);
        Flux::toast(variant: 'success', text: 'Client reminder approved and queued.');
    }

    /**
     * @return Collection<int, Obligation>
     */
    #[Computed]
    public function priorityObligations(): Collection
    {
        return $this->filterObligations($this->visibleObligations())
            ->with(['client', 'workItems' => static fn ($query) => $query->orderBy('created_at')])
            ->where('status', ObligationStatus::Open)
            ->whereRaw('coalesce(effective_due_date, statutory_due_date) <= ?', [today()->addDays($this->horizonDays)->toDateString()])
            ->orderByRaw('coalesce(effective_due_date, statutory_due_date)')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, WorkItem>
     */
    #[Computed]
    public function highRiskWork(): Collection
    {
        return $this->filterWorkItems($this->visibleWorkItems())
            ->with(['obligation.client'])
            ->where('risk_status', RiskLevel::High)
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled])
            ->orderBy('updated_at')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, PaymentRecord>
     */
    #[Computed]
    public function overduePayments(): Collection
    {
        return $this->filterPayments($this->visiblePayments())
            ->with(['obligation.client'])
            ->where('status', PaymentStatus::Overdue)
            ->orderBy('updated_at')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, WorkItem> */
    #[Computed]
    public function awaitingClientWork(): Collection
    {
        return $this->filterWorkItems($this->visibleWorkItems())
            ->with('obligation.client')
            ->whereIn('status', [WorkItemStatus::AwaitingClient, WorkItemStatus::AwaitingClientApproval])
            ->orderBy('updated_at')->limit(8)->get();
    }

    /** @return Collection<int, WorkItem> */
    #[Computed]
    public function underReviewWork(): Collection
    {
        return $this->filterWorkItems($this->visibleWorkItems())
            ->with('obligation.client')
            ->where('status', WorkItemStatus::UnderReview)
            ->orderBy('updated_at')->limit(8)->get();
    }

    /** @return Collection<int, WorkItem> */
    #[Computed]
    public function unassignedWork(): Collection
    {
        return $this->filterWorkItems($this->visibleWorkItems())
            ->with('obligation.client')
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled])
            ->whereDoesntHave('assignmentHistories')
            ->orderBy('created_at')->limit(8)->get();
    }

    /**
     * @return list<array{name: string, preparer: int, reviewer: int, manager: int, total: int}>
     */
    #[Computed]
    public function workloadByMember(): array
    {
        $workItems = $this->filterWorkItems($this->visibleWorkItems())
            ->with('assignmentHistories.assignedMembership.user')
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled])
            ->limit(500)->get();
        $workload = [];

        foreach ($workItems as $workItem) {
            foreach (AssignmentRole::cases() as $role) {
                $assignment = $workItem->currentAssignment($role);
                $membership = $assignment?->assignedMembership;
                if ($membership === null) {
                    continue;
                }
                $key = $membership->id;
                $workload[$key] ??= [
                    'name' => $membership->user->name,
                    'preparer' => 0,
                    'reviewer' => 0,
                    'manager' => 0,
                    'total' => 0,
                ];
                $column = match ($role) {
                    AssignmentRole::Preparer => 'preparer',
                    AssignmentRole::Reviewer => 'reviewer',
                    AssignmentRole::ResponsibleManager => 'manager',
                };
                $workload[$key][$column]++;
                $workload[$key]['total']++;
            }
        }
        usort($workload, static fn (array $left, array $right): int => [$right['total'], $left['name']] <=> [$left['total'], $right['name']]);

        return array_slice($workload, 0, 12);
    }

    public function render(): View
    {
        return view('livewire.dashboard.index');
    }

    /**
     * @return Builder<Obligation>
     */
    private function visibleObligations(): Builder
    {
        $membership = app(FirmContext::class)->membership();

        if ($membership === null) {
            return Obligation::query()->whereRaw('1 = 0');
        }

        if (
            $membership->hasPermission(Permission::ManageObligations)
            || $membership->hasPermission(Permission::AssignWork)
        ) {
            return Obligation::query();
        }

        if (
            ! $membership->hasPermission(Permission::PrepareWork)
            && ! $membership->hasPermission(Permission::ReviewWork)
        ) {
            return Obligation::query()->whereRaw('1 = 0');
        }

        return Obligation::query()->whereHas(
            'workItems.assignmentHistories',
            static fn (Builder $query): Builder => $query
                ->where('assigned_membership_id', $membership->id)
                ->whereRaw(
                    'assignment_histories.id = (
                        select max(latest_assignment.id)
                        from assignment_histories as latest_assignment
                        where latest_assignment.work_item_id = assignment_histories.work_item_id
                        and latest_assignment.assignment_role = assignment_histories.assignment_role
                    )',
                ),
        );
    }

    /**
     * @return Builder<WorkItem>
     */
    private function visibleWorkItems(): Builder
    {
        $membership = app(FirmContext::class)->membership();

        if ($membership === null) {
            return WorkItem::query()->whereRaw('1 = 0');
        }

        if ($membership->hasPermission(Permission::AssignWork)) {
            return WorkItem::query();
        }

        if (
            ! $membership->hasPermission(Permission::PrepareWork)
            && ! $membership->hasPermission(Permission::ReviewWork)
        ) {
            return WorkItem::query()->whereRaw('1 = 0');
        }

        return WorkItem::query()->whereHas(
            'assignmentHistories',
            static fn (Builder $query): Builder => $query
                ->where('assigned_membership_id', $membership->id)
                ->whereRaw(
                    'assignment_histories.id = (
                        select max(latest_assignment.id)
                        from assignment_histories as latest_assignment
                        where latest_assignment.work_item_id = assignment_histories.work_item_id
                        and latest_assignment.assignment_role = assignment_histories.assignment_role
                    )',
                ),
        );
    }

    /**
     * @return Builder<PaymentRecord>
     */
    private function visiblePayments(): Builder
    {
        $membership = app(FirmContext::class)->membership();

        if ($membership === null) {
            return PaymentRecord::query()->whereRaw('1 = 0');
        }

        if ($membership->hasPermission(Permission::ManagePayments)) {
            return PaymentRecord::query();
        }

        if (
            ! $membership->hasPermission(Permission::PrepareWork)
            && ! $membership->hasPermission(Permission::ReviewWork)
        ) {
            return PaymentRecord::query()->whereRaw('1 = 0');
        }

        return PaymentRecord::query()->whereHas(
            'obligation.workItems.assignmentHistories',
            static fn (Builder $query): Builder => $query
                ->where('assigned_membership_id', $membership->id)
                ->whereRaw(
                    'assignment_histories.id = (
                        select max(latest_assignment.id)
                        from assignment_histories as latest_assignment
                        where latest_assignment.work_item_id = assignment_histories.work_item_id
                        and latest_assignment.assignment_role = assignment_histories.assignment_role
                    )',
                ),
        );
    }

    /** @param Builder<Obligation> $query
     * @return Builder<Obligation>
     */
    private function filterObligations(Builder $query): Builder
    {
        return $query
            ->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId))
            ->when(trim($this->clientSearch) !== '', function (Builder $query): void {
                $search = '%'.trim($this->clientSearch).'%';
                $query->whereHas('client', static fn (Builder $client): Builder => $client
                    ->where('internal_code', 'like', $search)
                    ->orWhere('legal_name', 'like', $search));
            })
            ->when($this->category === 'vat', fn (Builder $query): Builder => $query->where('obligation_type', 'VAT Return'))
            ->when($this->category === 'corporate-tax', fn (Builder $query): Builder => $query->where('obligation_type', 'Corporate Tax Return'))
            ->when($this->category === 'documents', fn (Builder $query): Builder => $query->whereRaw('1 = 0'));
    }

    /** @param Builder<WorkItem> $query
     * @return Builder<WorkItem>
     */
    private function filterWorkItems(Builder $query): Builder
    {
        return $query->when(
            $this->clientId !== '',
            fn (Builder $query): Builder => $query->whereHas('obligation', fn (Builder $query): Builder => $query->where('client_id', $this->clientId)),
        );
    }

    /** @param Builder<PaymentRecord> $query
     * @return Builder<PaymentRecord>
     */
    private function filterPayments(Builder $query): Builder
    {
        return $query->when(
            $this->clientId !== '',
            fn (Builder $query): Builder => $query->whereHas('obligation', fn (Builder $query): Builder => $query->where('client_id', $this->clientId)),
        );
    }

    private function flushOperationalData(): void
    {
        unset(
            $this->summary, $this->priorityObligations, $this->highRiskWork, $this->overduePayments,
            $this->awaitingClientWork, $this->underReviewWork, $this->unassignedWork, $this->workloadByMember,
            $this->remindersAwaitingReview,
            $this->workloadStrip,
            $this->portfolioItems,
        );
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function monthRange(): array
    {
        $month = CarbonImmutable::createFromFormat('!Y-m', $this->selectedMonth)
            ?: CarbonImmutable::today()->startOfMonth();

        return [$month->startOfMonth(), $month->endOfMonth()];
    }
}
