<?php

declare(strict_types=1);

namespace App\Actions\Filings;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\FilingStatus;
use App\Models\FilingRecord;
use App\Models\FilingRecordTransition;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class TransitionFilingRecord
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * Move filing state only. Work status and payment status are never touched here.
     *
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        FilingRecord $filingRecord,
        FilingStatus $targetStatus,
        string $reason,
        ?string $filingReference = null,
        ?string $filedOn = null,
    ): FilingRecordTransition {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('transition', $filingRecord);

        /** @var array{targetStatus: string, reason: string, filingReference: string|null, filedOn: string|null} $validated */
        $validated = Validator::make(
            [
                'targetStatus' => $targetStatus->value,
                'reason' => $reason,
                'filingReference' => $filingReference,
                'filedOn' => $filedOn,
            ],
            [
                'targetStatus' => ['required', Rule::enum(FilingStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
                'filingReference' => ['nullable', 'string', 'max:100'],
                'filedOn' => ['nullable', 'date', 'before_or_equal:today'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $filingRecord, $targetStatus, $validated): FilingRecordTransition {
            $lockedFilingRecord = FilingRecord::query()
                ->lockForUpdate()
                ->findOrFail($filingRecord->id);
            $fromStatus = $lockedFilingRecord->status;

            if (! in_array($targetStatus, $lockedFilingRecord->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'targetStatus' => "Filing cannot move from {$fromStatus->label()} to {$targetStatus->label()}.",
                ]);
            }

            $resolvedReference = $this->trimToNull($validated['filingReference'])
                ?? $lockedFilingRecord->filing_reference;

            if ($targetStatus->requiresFilingReference() && $resolvedReference === null) {
                throw ValidationException::withMessages([
                    'filingReference' => 'Record the filing reference issued by the authority before moving to this state.',
                ]);
            }

            $resolvedFiledOn = $this->trimToNull($validated['filedOn'])
                ?? $lockedFilingRecord->filed_on?->toDateString();

            if ($targetStatus === FilingStatus::Filed && $resolvedFiledOn === null) {
                throw ValidationException::withMessages([
                    'filedOn' => 'Record the date the return was filed.',
                ]);
            }

            $transitionedAt = now('UTC');

            $transition = FilingRecordTransition::query()->create([
                'filing_record_id' => $lockedFilingRecord->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'transitioned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'transitioned_at' => $transitionedAt,
            ]);

            $lockedFilingRecord->update([
                'status' => $targetStatus,
                'filing_reference' => $resolvedReference,
                'filed_on' => $resolvedFiledOn,
            ]);

            $this->recordAudit->handle(
                action: 'filing_record.status_transitioned',
                actor: $actor,
                auditable: $lockedFilingRecord,
                before: ['status' => $fromStatus->value],
                after: [
                    'status' => $targetStatus->value,
                    'filing_reference' => $resolvedReference,
                    'filed_on' => $resolvedFiledOn,
                ],
                reason: trim($validated['reason']),
            );

            return $transition->refresh();
        }, 3);
    }

    private function trimToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
