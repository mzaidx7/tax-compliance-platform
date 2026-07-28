<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ObligationStatus;
use App\Models\Obligation;
use App\Models\ObligationDeadlineOverride;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class OverrideObligationDeadline
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array{effectiveDueDate: mixed, reason: mixed}  $input
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, Obligation $obligation, array $input): ObligationDeadlineOverride
    {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($obligation->firm_id !== $firmId) {
            throw new AuthorizationException('The obligation does not belong to the active firm.');
        }

        Gate::forUser($actor)->authorize('update', $obligation);

        /** @var array{effectiveDueDate: string, reason: string} $validated */
        $validated = Validator::make($input, [
            'effectiveDueDate' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $obligation, $validated): ObligationDeadlineOverride {
            $locked = Obligation::query()->lockForUpdate()->findOrFail($obligation->id);
            $previousDate = $locked->effectiveDueDate();
            $newDate = Carbon::createFromFormat('Y-m-d', $validated['effectiveDueDate'])->startOfDay();

            if ($locked->status !== ObligationStatus::Open) {
                throw ValidationException::withMessages(['effectiveDueDate' => 'Only an open obligation can have its effective deadline overridden.']);
            }
            if ($previousDate->isSameDay($newDate)) {
                throw ValidationException::withMessages(['effectiveDueDate' => 'The effective deadline must change.']);
            }
            if ($locked->internal_target_date?->greaterThan($newDate)) {
                throw ValidationException::withMessages(['effectiveDueDate' => 'The effective deadline cannot be before the internal target date.']);
            }

            $override = ObligationDeadlineOverride::query()->create([
                'obligation_id' => $locked->id,
                'previous_effective_due_date' => $previousDate,
                'new_effective_due_date' => $newDate,
                'reason' => trim($validated['reason']),
                'overridden_by' => $actor->id,
                'overridden_at' => now(),
            ]);

            $locked->update(['effective_due_date' => $newDate]);

            $this->recordAudit->handle(
                action: 'obligation.deadline_overridden',
                actor: $actor,
                auditable: $locked,
                before: [
                    'statutory_due_date' => $locked->statutory_due_date->toDateString(),
                    'effective_due_date' => $previousDate->toDateString(),
                ],
                after: [
                    'statutory_due_date' => $locked->statutory_due_date->toDateString(),
                    'effective_due_date' => $newDate->toDateString(),
                    'reason' => trim($validated['reason']),
                    'override_id' => $override->id,
                ],
            );

            return $override->refresh();
        }, 3);
    }
}
