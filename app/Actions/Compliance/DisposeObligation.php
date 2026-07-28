<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ObligationStatus;
use App\Models\Obligation;
use App\Models\ObligationDisposition;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class DisposeObligation
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /** @param array{status: mixed, replacementObligationId?: mixed, reason: mixed} $input */
    public function handle(User $actor, Obligation $obligation, array $input): ObligationDisposition
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($obligation->firm_id !== $firmId) {
            throw new AuthorizationException('The obligation does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $obligation);

        /** @var array{status: string, replacementObligationId: string|null, reason: string} $validated */
        $validated = Validator::make([...$input, 'replacementObligationId' => $input['replacementObligationId'] ?? null], [
            'status' => ['required', Rule::enum(ObligationStatus::class)->only([ObligationStatus::Cancelled, ObligationStatus::Superseded])],
            'replacementObligationId' => ['nullable', 'string', 'ulid'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $obligation, $validated): ObligationDisposition {
            $locked = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);
            if ($locked->status !== ObligationStatus::Open) {
                throw ValidationException::withMessages(['status' => 'Only an open obligation can be cancelled or superseded.']);
            }
            $target = ObligationStatus::from($validated['status']);
            $replacement = $validated['replacementObligationId'] === null
                ? null
                : Obligation::query()->whereKey($validated['replacementObligationId'])->firstOrFail();
            if ($target === ObligationStatus::Superseded && ($replacement === null || $replacement->id === $locked->id || $replacement->status !== ObligationStatus::Open)) {
                throw ValidationException::withMessages(['replacementObligationId' => 'Supersession requires a different open replacement obligation in the active firm.']);
            }
            if ($target === ObligationStatus::Cancelled && $replacement !== null) {
                throw ValidationException::withMessages(['replacementObligationId' => 'Cancellation cannot name a replacement obligation.']);
            }

            $event = ObligationDisposition::query()->create([
                'obligation_id' => $locked->id,
                'previous_status' => $locked->status,
                'new_status' => $target,
                'replacement_obligation_id' => $replacement?->id,
                'reason' => trim($validated['reason']),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $locked->update(['status' => $target]);
            $this->recordAudit->handle(
                action: 'obligation.disposed',
                actor: $actor,
                auditable: $locked,
                before: ['status' => ObligationStatus::Open->value],
                after: ['status' => $target->value, 'replacement_obligation_id' => $replacement?->id],
                reason: trim($validated['reason']),
            );

            return $event->refresh();
        }, 3);
    }
}
