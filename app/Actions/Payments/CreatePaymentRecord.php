<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\PaymentRecordTransition;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreatePaymentRecord
{
    /**
     * Statuses a payment may be opened in. A payment is never opened as paid or
     * overdue, because those assert a settlement outcome that must be recorded
     * through an explicit later transition with retained evidence.
     */
    private const OPENING_STATUSES = [
        PaymentStatus::NotRequired,
        PaymentStatus::Unknown,
        PaymentStatus::Pending,
    ];

    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        Obligation $obligation,
        PaymentStatus $status,
        string $reason,
    ): PaymentRecord {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        if ($obligation->firm_id !== $firmId) {
            throw new AuthorizationException('The obligation does not belong to the active firm.');
        }

        Gate::forUser($actor)->authorize('create', PaymentRecord::class);

        /** @var array{status: string, reason: string} $validated */
        $validated = Validator::make(
            ['status' => $status->value, 'reason' => $reason],
            [
                'status' => ['required', Rule::enum(PaymentStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        if (! in_array($status, self::OPENING_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'A payment record opens as not required, unknown or pending. Record any settlement outcome through a payment transition.',
            ]);
        }

        return DB::transaction(function () use ($actor, $obligation, $status, $validated): PaymentRecord {
            $lockedObligation = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (PaymentRecord::query()->whereBelongsTo($lockedObligation)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This obligation already has a payment record.',
                ]);
            }

            $paymentRecord = PaymentRecord::query()->create([
                'obligation_id' => $lockedObligation->id,
                'status' => $status,
                'payment_reference' => null,
                'paid_on' => null,
                'created_by' => $actor->id,
            ]);

            PaymentRecordTransition::query()->create([
                'payment_record_id' => $paymentRecord->id,
                'from_status' => null,
                'to_status' => $status,
                'transitioned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'transitioned_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'payment_record.created',
                actor: $actor,
                auditable: $paymentRecord,
                after: [
                    'obligation_id' => $lockedObligation->id,
                    'status' => $status->value,
                ],
                reason: trim($validated['reason']),
            );

            return $paymentRecord->refresh();
        }, 3);
    }
}
