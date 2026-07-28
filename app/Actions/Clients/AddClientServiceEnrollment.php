<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientService;
use App\Enums\Feature;
use App\Enums\FirmMembershipStatus;
use App\Enums\ServiceEnrollmentStatus;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class AddClientServiceEnrollment
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        ClientService $service,
        string $startsOn,
        ?string $endsOn,
        string $responsibleMembershipId,
    ): ClientServiceEnrollment {
        $firmId = $this->firmContext->firm()->id;
        $this->authorize($actor, $client, $firmId);

        /** @var array{starts_on: string, ends_on: string|null, responsible_membership_id: string} $validated */
        $validated = Validator::make(
            [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'responsible_membership_id' => $responsibleMembershipId,
            ],
            [
                'starts_on' => ['required', 'date'],
                'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
                'responsible_membership_id' => [
                    'required',
                    Rule::exists('firm_users', 'id')->where(
                        static fn ($query) => $query
                            ->where('firm_id', $firmId)
                            ->where('status', FirmMembershipStatus::Active->value),
                    ),
                ],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $client, $service, $validated): ClientServiceEnrollment {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);

            $enrollment = ClientServiceEnrollment::query()->create([
                'client_id' => $lockedClient->id,
                'service' => $service,
                'status' => ServiceEnrollmentStatus::Active,
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'responsible_membership_id' => $validated['responsible_membership_id'],
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'client.service_enrolled',
                actor: $actor,
                auditable: $client,
                after: [
                    'enrollment_id' => $enrollment->id,
                    'service' => $service->value,
                    'responsible_membership_id' => $validated['responsible_membership_id'],
                    'starts_on' => $validated['starts_on'],
                    'ends_on' => $validated['ends_on'],
                ],
            );

            return $enrollment->refresh();
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
