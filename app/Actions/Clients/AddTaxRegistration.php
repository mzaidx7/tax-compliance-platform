<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\TaxRegistration;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class AddTaxRegistration
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        TaxType $taxType,
        string $registrationNumber,
        TaxRegistrationStatus $status,
        ?string $effectiveFrom,
        ?string $effectiveTo,
    ): TaxRegistration {
        $firmId = $this->firmContext->firm()->id;
        $this->authorize($actor, $client, $firmId);
        $normalized = Str::upper(preg_replace('/\s+/', '', trim($registrationNumber)) ?? '');

        /** @var array{registration_number: string, effective_from: string|null, effective_to: string|null} $validated */
        $validated = Validator::make(
            [
                'registration_number' => $normalized,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
            ],
            [
                'registration_number' => [
                    'required',
                    'string',
                    'min:5',
                    'max:64',
                    'regex:/^[A-Z0-9-]+$/',
                    Rule::unique('tax_registrations', 'registration_number_normalized')->where(
                        static fn (Builder $query): Builder => $query
                            ->where('firm_id', $firmId)
                            ->where('tax_type', $taxType->value),
                    ),
                ],
                'effective_from' => ['nullable', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            ],
        )->validate();

        return DB::transaction(function () use (
            $actor,
            $client,
            $taxType,
            $status,
            $validated,
            $normalized,
        ): TaxRegistration {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);
            $registration = TaxRegistration::query()->create([
                'client_id' => $lockedClient->id,
                'tax_type' => $taxType,
                'registration_number' => $validated['registration_number'],
                'registration_number_normalized' => $normalized,
                'status' => $status,
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'],
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'client.tax_registration_added',
                actor: $actor,
                auditable: $client,
                after: [
                    'tax_registration_id' => $registration->id,
                    'tax_type' => $taxType->value,
                    'status' => $status->value,
                    'effective_from' => $validated['effective_from'],
                    'effective_to' => $validated['effective_to'],
                ],
            );

            return $registration->refresh();
        }, 3);
    }

    private function authorize(User $actor, Client $client, string $firmId): void
    {
        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }

        if ($client->firm_id !== $firmId) {
            throw new AuthorizationException('The client does not belong to the active firm.');
        }

        Gate::forUser($actor)->authorize('update', $client);
    }
}
