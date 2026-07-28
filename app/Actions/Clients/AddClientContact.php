<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\ClientContactPurpose;
use App\Enums\Feature;
use App\Enums\PreferredContactChannel;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AddClientContact
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        string $name,
        ?string $position,
        ClientContactPurpose $purpose,
        PreferredContactChannel $preferredChannel,
        ?string $email,
        ?string $phone,
    ): ClientContact {
        $firmId = $this->firmContext->firm()->id;
        $this->authorize($actor, $client, $firmId);

        /** @var array{name: string, position: string|null, email: string|null, phone: string|null} $validated */
        $validated = Validator::make(
            compact('name', 'position', 'email', 'phone'),
            [
                'name' => ['required', 'string', 'max:255'],
                'position' => ['nullable', 'string', 'max:100'],
                'email' => ['nullable', 'email:rfc', 'max:255'],
                'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+() .-]+$/'],
            ],
        )->validate();

        if (
            ($preferredChannel === PreferredContactChannel::Email && $validated['email'] === null)
            || (in_array($preferredChannel, [PreferredContactChannel::Phone, PreferredContactChannel::WhatsApp], true)
                && $validated['phone'] === null)
        ) {
            throw ValidationException::withMessages([
                'preferredChannel' => 'The preferred channel must have a matching contact detail.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $client,
            $validated,
            $purpose,
            $preferredChannel,
        ): ClientContact {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);
            $contact = ClientContact::query()->create([
                'client_id' => $lockedClient->id,
                'name' => trim($validated['name']),
                'position' => $this->optional($validated['position']),
                'purpose' => $purpose,
                'preferred_channel' => $preferredChannel,
                'email' => $this->optional($validated['email']),
                'phone' => $this->optional($validated['phone']),
                'is_active' => true,
                'created_by' => $actor->id,
            ]);

            $this->recordAudit->handle(
                action: 'client.contact_added',
                actor: $actor,
                auditable: $lockedClient,
                after: [
                    'contact_id' => $contact->id,
                    'purpose' => $purpose->value,
                    'preferred_channel' => $preferredChannel->value,
                ],
            );

            return $contact->refresh();
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

    private function optional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
