<?php

declare(strict_types=1);

namespace App\Actions\Taxes;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\TaxRecordStatus;
use App\Models\TaxRecord;
use App\Models\TaxRecordAmendment;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Amend the retained figures of a draft tax record, and optionally finalise it.
 *
 * A final record is terminal and cannot be amended. This action never infers a
 * statutory amount and never touches work, filing or payment state.
 */
final readonly class AmendTaxRecord
{
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
        TaxRecord $taxRecord,
        string $taxableAmount,
        string $taxAmount,
        TaxRecordStatus $targetStatus,
        string $reason,
    ): TaxRecordAmendment {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('amend', $taxRecord);

        /** @var array{taxableAmount: string, taxAmount: string, targetStatus: string, reason: string} $validated */
        $validated = Validator::make(
            [
                'taxableAmount' => $taxableAmount,
                'taxAmount' => $taxAmount,
                'targetStatus' => $targetStatus->value,
                'reason' => $reason,
            ],
            [
                'taxableAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'taxAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'targetStatus' => ['required', Rule::enum(TaxRecordStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $taxRecord, $targetStatus, $validated): TaxRecordAmendment {
            $lockedTaxRecord = TaxRecord::query()->lockForUpdate()->findOrFail($taxRecord->id);

            if ($lockedTaxRecord->status === TaxRecordStatus::Final) {
                throw ValidationException::withMessages([
                    'targetStatus' => 'A final tax record cannot be amended. Create a corrected record through a future controlled path.',
                ]);
            }

            $previousStatus = $lockedTaxRecord->status;
            $previousTaxable = $lockedTaxRecord->taxable_amount;
            $previousTax = $lockedTaxRecord->tax_amount;
            $amendedAt = now('UTC');

            $amendment = TaxRecordAmendment::query()->create([
                'tax_record_id' => $lockedTaxRecord->id,
                'previous_status' => $previousStatus,
                'previous_taxable_amount' => $previousTaxable,
                'previous_tax_amount' => $previousTax,
                'new_status' => $targetStatus,
                'new_taxable_amount' => $validated['taxableAmount'],
                'new_tax_amount' => $validated['taxAmount'],
                'amended_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'amended_at' => $amendedAt,
            ]);

            $lockedTaxRecord->update([
                'taxable_amount' => $validated['taxableAmount'],
                'tax_amount' => $validated['taxAmount'],
                'status' => $targetStatus,
            ]);

            $this->recordAudit->handle(
                action: 'tax_record.amended',
                actor: $actor,
                auditable: $lockedTaxRecord,
                before: [
                    'status' => $previousStatus->value,
                    'taxable_amount' => $previousTaxable,
                    'tax_amount' => $previousTax,
                ],
                after: [
                    'status' => $targetStatus->value,
                    'taxable_amount' => $validated['taxableAmount'],
                    'tax_amount' => $validated['taxAmount'],
                ],
                reason: trim($validated['reason']),
            );

            return $amendment->refresh();
        }, 3);
    }
}
