<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\FirmMembershipStatus;
use App\Enums\ObligationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\RiskLevel;
use App\Enums\WorkItemStatus;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\ManagerOperationalSummaryNotification;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class GenerateManagerOperationalSummary
{
    public function __construct(private FirmContext $context, private DispatchFirmNotification $dispatch) {}

    public function handle(User $actor, FirmMembership $recipientMembership): NotificationRequest
    {
        $actorMembership = $this->context->membership();
        if ($actorMembership?->user_id !== $actor->id || ! $actorMembership->hasPermission(Permission::ViewReports)) {
            throw new AuthorizationException('Report permission is required to generate a manager summary.');
        }
        if (
            $recipientMembership->firm_id !== $this->context->firmId()
            || $recipientMembership->status !== FirmMembershipStatus::Active
            || ! $recipientMembership->hasPermission(Permission::AssignWork)
        ) {
            throw new AuthorizationException('Select an active manager in the current firm.');
        }
        $recipient = $recipientMembership->user()->firstOrFail();
        $today = today();
        $dueSoon = Obligation::query()->where('status', ObligationStatus::Open)
            ->whereRaw('coalesce(effective_due_date, statutory_due_date) between ? and ?', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->count();
        $overdue = Obligation::query()->where('status', ObligationStatus::Open)
            ->whereRaw('coalesce(effective_due_date, statutory_due_date) < ?', [$today->toDateString()])
            ->count();
        $highRisk = WorkItem::query()->where('risk_status', RiskLevel::High)
            ->whereNotIn('status', [WorkItemStatus::Completed, WorkItemStatus::Cancelled])->count();
        $overduePayments = PaymentRecord::query()->where('status', PaymentStatus::Overdue)->count();

        return $this->dispatch->handle(
            $recipient,
            new ManagerOperationalSummaryNotification(
                $this->context->firmId(), $recipient->id, $dueSoon, $overdue, $highRisk, $overduePayments, $today->toDateString(),
            ),
            "manager-summary:{$recipientMembership->id}:{$today->toDateString()}",
            $actor,
        );
    }
}
