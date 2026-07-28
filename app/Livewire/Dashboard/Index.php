<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Actions\Operations\DeleteOperationalFilter;
use App\Actions\Operations\SaveOperationalFilter;
use App\Enums\AssignmentRole;
use App\Enums\ClientStatus;
use App\Enums\ObligationStatus;
use App\Enums\OperationalFilterSurface;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\Client;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\SavedOperationalFilter;
use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
final class Index extends Component
{
    public string $clientId = '';

    public int $horizonDays = 30;

    public string $savedFilterName = '';

    public string $selectedSavedFilterId = '';

    public function updatedHorizonDays(): void
    {
        $this->validate(['horizonDays' => ['required', 'integer', Rule::in([7, 14, 30, 60, 90])]]);
        $this->flushOperationalData();
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
            ['client_id' => $this->clientId, 'horizon_days' => $this->horizonDays],
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
        return Client::query()->where('status', ClientStatus::Active)->orderBy('legal_name')->get();
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

    /**
     * @return array{
     *  due_soon: int, overdue: int, high_risk: int, overdue_payments: int,
     *  awaiting_client: int, under_review: int, unassigned: int, active_workload: int
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
        ];
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
        return $query->when($this->clientId !== '', fn (Builder $query): Builder => $query->where('client_id', $this->clientId));
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
        );
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
