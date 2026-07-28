<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTypeVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordClientDocumentMetadata
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        Client $client,
        DocumentTypeVersion $documentType,
        ?string $referenceLabel,
        ?string $issuedOn,
        ?string $expiresOn,
        ?ClientDocument $supersedes = null,
    ): ClientDocument {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ClientMaster, $firmId)) {
            throw new AuthorizationException('The client master is not enabled for this firm.');
        }
        if ($client->firm_id !== $firmId || $documentType->firm_id !== $firmId) {
            throw new AuthorizationException('The client and document type must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $client);

        /** @var array{reference_label: string|null, issued_on: string|null, expires_on: string|null} $validated */
        $validated = Validator::make(
            [
                'reference_label' => $referenceLabel,
                'issued_on' => $issuedOn,
                'expires_on' => $expiresOn,
            ],
            [
                'reference_label' => ['nullable', 'string', 'max:100'],
                'issued_on' => ['nullable', 'date'],
                'expires_on' => [
                    $documentType->expiry_required ? 'required' : 'nullable',
                    'date',
                    'after_or_equal:issued_on',
                ],
            ],
        )->validate();

        return DB::transaction(function () use (
            $actor,
            $client,
            $documentType,
            $validated,
            $supersedes,
        ): ClientDocument {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);
            $lockedType = DocumentTypeVersion::query()->findOrFail($documentType->id);
            $lockedPredecessor = null;

            if ($supersedes instanceof ClientDocument) {
                $lockedPredecessor = ClientDocument::query()
                    ->with(['documentTypeVersion', 'successor'])
                    ->lockForUpdate()
                    ->findOrFail($supersedes->id);

                if (
                    $lockedPredecessor->client_id !== $lockedClient->id
                    || $lockedPredecessor->documentTypeVersion->key !== $lockedType->key
                    || $lockedPredecessor->successor !== null
                ) {
                    throw ValidationException::withMessages([
                        'supersedes' => 'A renewal must replace one current document of the same client and type.',
                    ]);
                }
            }

            $document = ClientDocument::query()->create([
                'client_id' => $lockedClient->id,
                'document_type_version_id' => $lockedType->id,
                'supersedes_client_document_id' => $lockedPredecessor?->id,
                'reference_label' => $this->optional($validated['reference_label']),
                'issued_on' => $validated['issued_on'],
                'expires_on' => $validated['expires_on'],
                'created_by' => $actor->id,
                'recorded_at' => now('UTC'),
            ]);

            $this->recordAudit->handle(
                action: 'client.document_metadata_recorded',
                actor: $actor,
                auditable: $lockedClient,
                after: [
                    'client_document_id' => $document->id,
                    'document_type_key' => $lockedType->key,
                    'document_type_version' => $lockedType->version,
                    'supersedes_client_document_id' => $lockedPredecessor?->id,
                ],
            );

            return $document->refresh();
        }, 3);
    }

    private function optional(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
