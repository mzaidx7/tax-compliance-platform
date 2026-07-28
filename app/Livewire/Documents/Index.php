<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Actions\Documents\PublishDocumentTypeVersion;
use App\Actions\Documents\RecordClientDocumentMetadata;
use App\Enums\Feature;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\DocumentTypeVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Document expiry')]
final class Index extends Component
{
    public string $typeKey = '';

    public string $typeName = '';

    public bool $expiryRequired = true;

    public string $reminderDays = '90, 60, 30, 14, 7, 1';

    public string $overdueRepeatDays = '7';

    public string $clientId = '';

    public string $documentTypeVersionId = '';

    public string $supersedesClientDocumentId = '';

    public string $referenceLabel = '';

    public string $issuedOn = '';

    public string $expiresOn = '';

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless($featureFlags->enabled(Feature::ClientMaster, $firmContext->firmId()), 404);
        Gate::authorize('viewAny', Client::class);
    }

    public function publishType(PublishDocumentTypeVersion $action): void
    {
        $type = $action->handle(
            $this->currentUser(),
            $this->typeKey,
            $this->typeName,
            $this->expiryRequired,
            $this->parseReminderDays(),
            $this->optionalInteger($this->overdueRepeatDays),
        );

        $this->reset('typeKey', 'typeName');
        $this->documentTypeVersionId = $type->id;
        unset($this->latestDocumentTypes);
        Flux::toast(variant: 'success', text: "Document type {$type->name} v{$type->version} published.");
    }

    public function recordDocument(RecordClientDocumentMetadata $action): void
    {
        $client = Client::query()->findOrFail($this->clientId);
        $type = DocumentTypeVersion::query()->findOrFail($this->documentTypeVersionId);
        $supersedes = $this->supersedesClientDocumentId === ''
            ? null
            : ClientDocument::query()->findOrFail($this->supersedesClientDocumentId);

        $document = $action->handle(
            $this->currentUser(),
            $client,
            $type,
            $this->optional($this->referenceLabel),
            $this->optional($this->issuedOn),
            $this->optional($this->expiresOn),
            $supersedes,
        );

        $this->reset(
            'supersedesClientDocumentId',
            'referenceLabel',
            'issuedOn',
            'expiresOn',
        );
        unset($this->currentDocuments);
        Flux::toast(variant: 'success', text: "Metadata recorded for {$document->documentTypeVersion->name}.");
    }

    /** @return Collection<int, Client> */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()->orderBy('legal_name')->orderBy('id')->get();
    }

    /** @return Collection<int, DocumentTypeVersion> */
    #[Computed]
    public function latestDocumentTypes(): Collection
    {
        return DocumentTypeVersion::query()
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->unique('key')
            ->values();
    }

    /** @return Collection<int, ClientDocument> */
    #[Computed]
    public function currentDocuments(): Collection
    {
        return ClientDocument::query()
            ->with(['client', 'documentTypeVersion', 'expiryReminders'])
            ->whereDoesntHave('successor')
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.documents.index');
    }

    /** @return list<int> */
    private function parseReminderDays(): array
    {
        if (trim($this->reminderDays) === '') {
            return [];
        }

        return array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', $this->reminderDays),
        );
    }

    private function optional(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function optionalInteger(string $value): ?int
    {
        return trim($value) === '' ? null : (int) $value;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
