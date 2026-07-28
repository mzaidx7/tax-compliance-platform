<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Models\Client;
use App\Models\Obligation;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class CreateManualObligation
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  array{
     *     clientId: mixed,
     *     obligationType: mixed,
     *     periodLabel?: mixed,
     *     statutoryDueDate: mixed,
     *     internalTargetDate?: mixed,
     *     sourceReference: mixed,
     *     lastVerifiedOn: mixed
     * }  $input
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, array $input): Obligation
    {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('create', Obligation::class);

        /** @var array{
         *     clientId: string,
         *     obligationType: string,
         *     periodLabel: string|null,
         *     statutoryDueDate: string,
         *     internalTargetDate: string|null,
         *     sourceReference: string,
         *     lastVerifiedOn: string
         * } $validated
         */
        $validated = Validator::make(
            [
                ...$input,
                'periodLabel' => $input['periodLabel'] ?? null,
                'internalTargetDate' => $input['internalTargetDate'] ?? null,
            ],
            [
                'clientId' => ['required', 'string', 'ulid'],
                'obligationType' => ['required', 'string', 'max:100'],
                'periodLabel' => ['nullable', 'string', 'max:100'],
                'statutoryDueDate' => ['required', 'date_format:Y-m-d'],
                'internalTargetDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:statutoryDueDate'],
                'sourceReference' => ['required', 'string', 'max:1000'],
                'lastVerifiedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            ],
            [
                'internalTargetDate.before_or_equal' => 'The internal target date must be on or before the statutory due date.',
                'lastVerifiedOn.before_or_equal' => 'The verification date cannot be in the future.',
            ],
        )->validate();

        $client = Client::query()->findOrFail($validated['clientId']);

        return DB::transaction(function () use ($actor, $client, $validated): Obligation {
            $obligation = Obligation::query()->create([
                'client_id' => $client->id,
                'obligation_type' => trim($validated['obligationType']),
                'period_label' => $this->optionalText($validated['periodLabel']),
                'statutory_due_date' => $validated['statutoryDueDate'],
                'internal_target_date' => $validated['internalTargetDate'],
                'origin' => ObligationOrigin::Manual,
                'status' => ObligationStatus::Open,
                'source_reference' => trim($validated['sourceReference']),
                'last_verified_on' => $validated['lastVerifiedOn'],
                'verified_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'obligation.manual_created',
                actor: $actor,
                auditable: $obligation,
                after: [
                    'client_id' => $client->id,
                    'obligation_type' => $obligation->obligation_type,
                    'statutory_due_date' => $obligation->statutory_due_date->toDateString(),
                    'internal_target_date' => $obligation->internal_target_date?->toDateString(),
                    'origin' => $obligation->origin->value,
                    'status' => $obligation->status->value,
                    'last_verified_on' => $obligation->last_verified_on->toDateString(),
                ],
            );

            return $obligation->refresh();
        }, 3);
    }

    private function optionalText(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
