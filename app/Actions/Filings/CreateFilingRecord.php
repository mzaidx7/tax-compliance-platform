<?php

declare(strict_types=1);

namespace App\Actions\Filings;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\FilingStatus;
use App\Models\FilingRecord;
use App\Models\FilingRecordTransition;
use App\Models\Obligation;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreateFilingRecord
{
    /**
     * Statuses a filing may be opened in. A filing is never opened as already
     * acknowledged, rejected or corrected, because those assert an authority
     * outcome that must be recorded through an explicit later transition.
     */
    private const OPENING_STATUSES = [FilingStatus::NotRequired, FilingStatus::NotFiled];

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
        FilingStatus $status,
        string $reason,
    ): FilingRecord {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        if ($obligation->firm_id !== $firmId) {
            throw new AuthorizationException('The obligation does not belong to the active firm.');
        }

        Gate::forUser($actor)->authorize('create', FilingRecord::class);

        /** @var array{status: string, reason: string} $validated */
        $validated = Validator::make(
            ['status' => $status->value, 'reason' => $reason],
            [
                'status' => ['required', Rule::enum(FilingStatus::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        if (! in_array($status, self::OPENING_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'A filing record opens as not required or not filed. Record any later outcome through a filing transition.',
            ]);
        }

        return DB::transaction(function () use ($actor, $obligation, $status, $validated): FilingRecord {
            $lockedObligation = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);

            if (FilingRecord::query()->whereBelongsTo($lockedObligation)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This obligation already has a filing record.',
                ]);
            }

            $filingRecord = FilingRecord::query()->create([
                'obligation_id' => $lockedObligation->id,
                'status' => $status,
                'filing_reference' => null,
                'filed_on' => null,
                'created_by' => $actor->id,
            ]);

            FilingRecordTransition::query()->create([
                'filing_record_id' => $filingRecord->id,
                'from_status' => null,
                'to_status' => $status,
                'transitioned_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'transitioned_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'filing_record.created',
                actor: $actor,
                auditable: $filingRecord,
                after: [
                    'obligation_id' => $lockedObligation->id,
                    'status' => $status->value,
                ],
                reason: trim($validated['reason']),
            );

            return $filingRecord->refresh();
        }, 3);
    }
}
