<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\ClientStatusChange;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class TransitionClientStatus
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, Client $client, ClientStatus $target, string $reason): ClientStatusChange
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }
        if ($client->firm_id !== $firmId) {
            throw new AuthorizationException('The client does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $client);

        /** @var array{reason: string} $validated */
        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $client, $target, $validated): ClientStatusChange {
            $locked = Client::query()->lockForUpdate()->findOrFail($client->id);
            $previous = $locked->status;
            if ($previous === $target) {
                throw ValidationException::withMessages(['clientStatus' => 'Choose a different client status.']);
            }

            $change = ClientStatusChange::query()->create([
                'client_id' => $locked->id,
                'previous_status' => $previous,
                'new_status' => $target,
                'changed_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'changed_at' => now('UTC'),
            ]);
            $locked->update(['status' => $target]);
            $this->recordAudit->handle(
                action: 'client.status_changed',
                actor: $actor,
                auditable: $locked,
                before: ['status' => $previous->value],
                after: ['status' => $target->value],
                reason: trim($validated['reason']),
            );

            return $change->refresh();
        }, 3);
    }
}
