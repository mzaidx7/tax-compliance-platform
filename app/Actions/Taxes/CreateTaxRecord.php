<?php

declare(strict_types=1);

namespace App\Actions\Taxes;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\TaxRecordStatus;
use App\Enums\TaxType;
use App\Models\Obligation;
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
 * Open a draft tax record with retained figures.
 *
 * Amounts are entered or externally computed values kept exactly as recorded.
 * This action never infers a statutory amount and never touches work, filing or
 * payment state.
 */
final readonly class CreateTaxRecord
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
        Obligation $obligation,
        TaxType $taxType,
        string $periodLabel,
        string $currency,
        string $taxableAmount,
        string $taxAmount,
        string $reason,
    ): TaxRecord {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        if ($obligation->firm_id !== $firmId) {
            throw new AuthorizationException('The obligation does not belong to the active firm.');
        }

        Gate::forUser($actor)->authorize('create', TaxRecord::class);

        /** @var array{taxType: string, periodLabel: string, currency: string, taxableAmount: string, taxAmount: string, reason: string} $validated */
        $validated = Validator::make(
            [
                'taxType' => $taxType->value,
                'periodLabel' => $periodLabel,
                'currency' => $currency,
                'taxableAmount' => $taxableAmount,
                'taxAmount' => $taxAmount,
                'reason' => $reason,
            ],
            [
                'taxType' => ['required', Rule::enum(TaxType::class)],
                'periodLabel' => ['required', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3', 'alpha'],
                'taxableAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'taxAmount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $obligation, $taxType, $validated): TaxRecord {
            $lockedObligation = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (TaxRecord::query()->whereBelongsTo($lockedObligation)->exists()) {
                throw ValidationException::withMessages([
                    'taxType' => 'This obligation already has a tax record.',
                ]);
            }

            $taxRecord = TaxRecord::query()->create([
                'obligation_id' => $lockedObligation->id,
                'tax_type' => $taxType,
                'period_label' => trim($validated['periodLabel']),
                'currency' => strtoupper($validated['currency']),
                'taxable_amount' => $validated['taxableAmount'],
                'tax_amount' => $validated['taxAmount'],
                'status' => TaxRecordStatus::Draft,
                'created_by' => $actor->id,
            ]);

            TaxRecordAmendment::query()->create([
                'tax_record_id' => $taxRecord->id,
                'previous_status' => null,
                'previous_taxable_amount' => null,
                'previous_tax_amount' => null,
                'new_status' => TaxRecordStatus::Draft,
                'new_taxable_amount' => $validated['taxableAmount'],
                'new_tax_amount' => $validated['taxAmount'],
                'amended_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'amended_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'tax_record.created',
                actor: $actor,
                auditable: $taxRecord,
                after: [
                    'obligation_id' => $lockedObligation->id,
                    'tax_type' => $taxType->value,
                    'status' => TaxRecordStatus::Draft->value,
                    'taxable_amount' => $validated['taxableAmount'],
                    'tax_amount' => $validated['taxAmount'],
                ],
                reason: trim($validated['reason']),
            );

            return $taxRecord->refresh();
        }, 3);
    }
}
