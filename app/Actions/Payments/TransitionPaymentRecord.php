<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Audit\RecordAudit;
use App\Actions\Notifications\DispatchFirmNotification;
use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\PaymentRecord;
use App\Models\PaymentRecordTransition;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\PaymentOverdueNotification;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class TransitionPaymentRecord
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
        private DispatchFirmNotification $dispatchFirmNotification,
    ) {}

    /**
     * Move payment state only. Work status and filing status are never touched here.
     *
     * This records an operator observation about a settlement that happened
     * elsewhere. It never initiates, authorises or confirms a real transfer.
     *
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        PaymentRecord $paymentRecord,
        PaymentStatus $targetStatus,
        string $reason,
        ?string $paymentReference = null,
        ?string $paidOn = null,
    ): PaymentRecordTransition {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('transition', $paymentRecord);

        /** @var array{targetStatus: string, reason: string, paymentReference: string|null, paidOn: string|null} $validated */
        $validated = Validator::make(
            [
                'targetStatus' => $targetStatus->value,
                'reason' => $reason,
                'paymentReference' => $paymentReference,
                'paidOn' => $paidOn,
            ],
            [
                'targetStatus' => ['required', Rule::enum(PaymentStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
                'paymentReference' => ['nullable', 'string', 'max:100'],
                'paidOn' => ['nullable', 'date', 'before_or_equal:today'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $paymentRecord, $targetStatus, $validated): PaymentRecordTransition {
            $lockedPaymentRecord = PaymentRecord::query()
                ->lockForUpdate()
                ->findOrFail($paymentRecord->id);
            $fromStatus = $lockedPaymentRecord->status;

            if (! in_array($targetStatus, $lockedPaymentRecord->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'targetStatus' => "Payment cannot move from {$fromStatus->label()} to {$targetStatus->label()}.",
                ]);
            }

            $resolvedReference = $this->trimToNull($validated['paymentReference'])
                ?? $lockedPaymentRecord->payment_reference;
            $resolvedPaidOn = $this->trimToNull($validated['paidOn'])
                ?? $lockedPaymentRecord->paid_on?->toDateString();

            if ($targetStatus->requiresPaymentEvidence()) {
                if ($resolvedReference === null) {
                    throw ValidationException::withMessages([
                        'paymentReference' => 'Record the payment reference from the settlement evidence before marking this paid.',
                    ]);
                }

                if ($resolvedPaidOn === null) {
                    throw ValidationException::withMessages([
                        'paidOn' => 'Record the date the payment settled.',
                    ]);
                }
            }

            $transitionedAt = now('UTC');

            $transition = PaymentRecordTransition::query()->create([
                'payment_record_id' => $lockedPaymentRecord->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'transitioned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'transitioned_at' => $transitionedAt,
            ]);

            $lockedPaymentRecord->update([
                'status' => $targetStatus,
                'payment_reference' => $resolvedReference,
                'paid_on' => $resolvedPaidOn,
            ]);

            $this->recordAudit->handle(
                action: 'payment_record.status_transitioned',
                actor: $actor,
                auditable: $lockedPaymentRecord,
                before: ['status' => $fromStatus->value],
                after: [
                    'status' => $targetStatus->value,
                    'payment_reference' => $resolvedReference,
                    'paid_on' => $resolvedPaidOn,
                ],
                reason: trim($validated['reason']),
            );

            $this->notifyResponsibleManager($actor, $lockedPaymentRecord, $targetStatus, $transition->id);

            return $transition->refresh();
        }, 3);
    }

    /**
     * Notify the accountable manager that a payment is now recorded overdue.
     *
     * Only an explicit move to overdue notifies. The accountable member is the
     * responsible manager of the obligation's primary work item. When no work
     * item or no active manager exists there is nobody valid to address, so the
     * payment change is still recorded and the notification is skipped.
     */
    private function notifyResponsibleManager(
        User $actor,
        PaymentRecord $paymentRecord,
        PaymentStatus $targetStatus,
        string $transitionId,
    ): void {
        if ($targetStatus !== PaymentStatus::Overdue) {
            return;
        }

        $workItem = WorkItem::query()
            ->where('obligation_id', $paymentRecord->obligation_id)
            ->whereNull('parent_work_item_id')
            ->first();

        if (! $workItem instanceof WorkItem) {
            return;
        }

        $recipient = $workItem->responsibleManagerUser();

        if (! $recipient instanceof User) {
            return;
        }

        $this->dispatchFirmNotification->handle(
            recipient: $recipient,
            notification: new PaymentOverdueNotification(
                $paymentRecord->firm_id,
                (int) $recipient->getKey(),
                $paymentRecord->id,
            ),
            idempotencyKey: "payment-overdue:{$transitionId}",
            actor: $actor,
        );
    }

    private function trimToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
