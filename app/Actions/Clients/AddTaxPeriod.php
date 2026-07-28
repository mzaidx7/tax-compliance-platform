<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\TaxPeriodStatus;
use App\Models\TaxPeriod;
use App\Models\TaxRegistration;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AddTaxPeriod
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        TaxRegistration $registration,
        string $label,
        string $startsOn,
        string $endsOn,
    ): TaxPeriod {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }

        if ($registration->firm_id !== $firmId) {
            throw new AuthorizationException('The tax registration does not belong to the active firm.');
        }

        $registration->loadMissing('client');
        Gate::forUser($actor)->authorize('update', $registration->client);

        /** @var array{label: string, starts_on: string, ends_on: string} $validated */
        $validated = Validator::make(
            ['label' => $label, 'starts_on' => $startsOn, 'ends_on' => $endsOn],
            [
                'label' => ['required', 'string', 'max:100'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $registration, $validated): TaxPeriod {
            $locked = TaxRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $overlaps = TaxPeriod::query()
                ->whereBelongsTo($locked, 'registration')
                ->whereDate('starts_on', '<=', $validated['ends_on'])
                ->whereDate('ends_on', '>=', $validated['starts_on'])
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'period' => 'This actual tax period overlaps a period already recorded for the registration.',
                ]);
            }

            $period = TaxPeriod::query()->create([
                'tax_registration_id' => $locked->id,
                'label' => trim($validated['label']),
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'status' => TaxPeriodStatus::Open,
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'client.tax_period_added',
                actor: $actor,
                auditable: $locked->client,
                after: [
                    'tax_registration_id' => $locked->id,
                    'tax_period_id' => $period->id,
                    'label' => $period->label,
                    'starts_on' => $validated['starts_on'],
                    'ends_on' => $validated['ends_on'],
                ],
            );

            return $period->refresh();
        }, 3);
    }
}
