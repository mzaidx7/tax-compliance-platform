<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\ObligationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
final class Index extends Component
{
    /**
     * @return array{due_soon: int, overdue: int, high_risk: int, overdue_payments: int}
     */
    #[Computed]
    public function summary(): array
    {
        $obligations = $this->visibleObligations()
            ->where('status', ObligationStatus::Open);
        $workItems = $this->visibleWorkItems()
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled]);
        $payments = $this->visiblePayments();

        return [
            'due_soon' => (clone $obligations)
                ->whereBetween('statutory_due_date', [today(), today()->addDays(30)])
                ->count(),
            'overdue' => (clone $obligations)
                ->whereDate('statutory_due_date', '<', today())
                ->count(),
            'high_risk' => (clone $workItems)
                ->where('risk_status', RiskLevel::High)
                ->count(),
            'overdue_payments' => (clone $payments)
                ->where('status', PaymentStatus::Overdue)
                ->count(),
        ];
    }

    /**
     * @return Collection<int, Obligation>
     */
    #[Computed]
    public function priorityObligations(): Collection
    {
        return $this->visibleObligations()
            ->with(['client', 'workItems' => static fn ($query) => $query->orderBy('created_at')])
            ->where('status', ObligationStatus::Open)
            ->whereDate('statutory_due_date', '<=', today()->addDays(30))
            ->orderBy('statutory_due_date')
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
        return $this->visibleWorkItems()
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
        return $this->visiblePayments()
            ->with(['obligation.client'])
            ->where('status', PaymentStatus::Overdue)
            ->orderBy('updated_at')
            ->limit(5)
            ->get();
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
}
