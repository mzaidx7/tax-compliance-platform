<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAudit;
use App\Contracts\MalwareScanner;
use App\Enums\DocumentPurpose;
use App\Enums\Feature;
use App\Enums\MalwareScanVerdict;
use App\Enums\WorkItemStatus;
use App\Models\DocumentEvidence;
use App\Models\DocumentScanEvent;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class StoreDocumentEvidence
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private TenantStorage $storage,
        private MalwareScanner $scanner,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $actor,
        WorkItem $workItem,
        DocumentPurpose $purpose,
        UploadedFile $file,
    ): DocumentEvidence {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        if ($workItem->firm_id !== $firmId) {
            throw new AuthorizationException('The work item does not belong to the active firm.');
        }

        $workItem->loadMissing('assignmentHistories');
        Gate::forUser($actor)->authorize('evidence', $workItem);

        if (in_array($workItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'document' => 'Evidence can be added only while work is open.',
            ]);
        }

        $validated = $this->validateFile($file);
        $sha256 = hash_file('sha256', $file->getRealPath());

        if (! is_string($sha256)) {
            throw ValidationException::withMessages(['document' => 'The document checksum could not be calculated.']);
        }

        $logicalPath = 'documents/'.Str::lower((string) Str::ulid()).'.'.$validated['extension'];
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['document' => 'The document could not be read.']);
        }

        try {
            $this->storage->writeStream($logicalPath, $stream);
        } finally {
            fclose($stream);
        }

        try {
            $evidence = DB::transaction(function () use (
                $actor,
                $workItem,
                $purpose,
                $logicalPath,
                $validated,
                $sha256,
            ): DocumentEvidence {
                $lockedWorkItem = WorkItem::query()->lockForUpdate()->findOrFail($workItem->id);

                if (in_array($lockedWorkItem->status, [WorkItemStatus::Completed, WorkItemStatus::Cancelled], true)) {
                    throw ValidationException::withMessages([
                        'document' => 'Evidence can be added only while work is open.',
                    ]);
                }

                $evidence = DocumentEvidence::query()->create([
                    'work_item_id' => $lockedWorkItem->id,
                    'purpose' => $purpose,
                    'original_name' => $validated['original_name'],
                    'extension' => $validated['extension'],
                    'detected_mime_type' => $validated['mime'],
                    'logical_path' => $logicalPath,
                    'sha256' => $sha256,
                    'bytes' => $validated['bytes'],
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now('UTC'),
                ]);

                $this->recordAudit->handle(
                    action: 'document_evidence.uploaded',
                    actor: $actor,
                    auditable: $evidence,
                    after: [
                        'work_item_id' => $lockedWorkItem->id,
                        'purpose' => $purpose->value,
                        'detected_mime_type' => $validated['mime'],
                        'bytes' => $validated['bytes'],
                        'sha256' => $evidence->sha256,
                    ],
                );

                return $evidence;
            }, 3);
        } catch (Throwable $exception) {
            $this->storage->delete($logicalPath);
            throw $exception;
        }

        $verdict = $this->scan($logicalPath);

        DocumentScanEvent::query()->create([
            'document_evidence_id' => $evidence->id,
            'verdict' => $verdict,
            'scanner' => class_basename($this->scanner),
            'scanned_at' => now('UTC'),
        ]);

        if ($verdict === MalwareScanVerdict::Infected) {
            $this->storage->delete($logicalPath);
        }

        $this->recordAudit->handle(
            action: 'document_evidence.scanned',
            actor: $actor,
            auditable: $evidence,
            after: ['verdict' => $verdict->value],
        );

        return $evidence->load('scanEvents');
    }

    /**
     * @return array{extension: string, mime: string, bytes: int, original_name: string}
     */
    private function validateFile(UploadedFile $file): array
    {
        $maxBytes = config('platform.documents.max_bytes');
        $allowedTypes = config('platform.documents.allowed_types');
        $bytes = $file->getSize();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        if (
            ! $file->isValid()
            || ! is_int($maxBytes)
            || ! is_array($allowedTypes)
            || ! is_int($bytes)
            || $bytes < 1
            || $bytes > $maxBytes
        ) {
            throw ValidationException::withMessages([
                'document' => 'The document is empty, unreadable or exceeds the configured size limit.',
            ]);
        }

        $allowedMimes = $allowedTypes[$extension] ?? null;

        if (! is_array($allowedMimes) || ! is_string($mime) || ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'document' => 'The document extension and detected file type are not allowed.',
            ]);
        }

        if (
            $originalName === ''
            || strlen($originalName) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1
        ) {
            throw ValidationException::withMessages([
                'document' => 'The original document name is invalid.',
            ]);
        }

        return [
            'extension' => $extension,
            'mime' => $mime,
            'bytes' => $bytes,
            'original_name' => $originalName,
        ];
    }

    private function scan(string $logicalPath): MalwareScanVerdict
    {
        try {
            return $this->scanner->scan($logicalPath);
        } catch (Throwable) {
            return MalwareScanVerdict::Unavailable;
        }
    }
}
