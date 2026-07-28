<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ServiceEnrollmentStatus;
use App\Models\ClientServiceEnrollment;
use App\Models\ClientServiceEnrollmentStatusChange;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class TransitionClientServiceEnrollment
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        ClientServiceEnrollment $enrollment,
        ServiceEnrollmentStatus $target,
        string $effectiveOn,
        string $reason,
    ): ClientServiceEnrollmentStatusChange {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }
        if ($enrollment->firm_id !== $firmId) {
            throw new AuthorizationException('The service enrollment does not belong to the active firm.');
        }
        $enrollment->loadMissing('client');
        Gate::forUser($actor)->authorize('update', $enrollment->client);

        /** @var array{effective_on: string, reason: string} $validated */
        $validated = Validator::make(
            ['effective_on' => $effectiveOn, 'reason' => $reason],
            [
                'effective_on' => ['required', 'date', 'after_or_equal:'.$enrollment->starts_on->toDateString()],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $enrollment, $target, $validated): ClientServiceEnrollmentStatusChange {
            $locked = ClientServiceEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $previous = $locked->status;
            if (! in_array($target, $previous->allowedTargets(), true)) {
                throw ValidationException::withMessages([
                    'serviceStatus' => "The service cannot move from {$previous->label()} to {$target->label()}.",
                ]);
            }

            $change = ClientServiceEnrollmentStatusChange::query()->create([
                'client_service_enrollment_id' => $locked->id,
                'previous_status' => $previous,
                'new_status' => $target,
                'effective_on' => $validated['effective_on'],
                'changed_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'changed_at' => now('UTC'),
            ]);
            $updates = ['status' => $target];
            if ($target === ServiceEnrollmentStatus::Ended) {
                $updates['ends_on'] = $validated['effective_on'];
            }
            $locked->update($updates);
            $this->recordAudit->handle(
                action: 'client.service_status_changed',
                actor: $actor,
                auditable: $locked->client,
                before: ['status' => $previous->value],
                after: [
                    'enrollment_id' => $locked->id,
                    'status' => $target->value,
                    'effective_on' => $validated['effective_on'],
                ],
                reason: trim($validated['reason']),
            );

            return $change->refresh();
        }, 3);
    }
}
