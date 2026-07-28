<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAudit;
use App\Enums\MalwareScanVerdict;
use App\Models\DocumentEvidence;
use App\Models\User;
use App\Tenancy\TenantStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;

final readonly class AuthorizeDocumentDownload
{
    public function __construct(
        private TenantStorage $storage,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, DocumentEvidence $evidence): DocumentEvidence
    {
        $evidence->loadMissing(['workItem.assignmentHistories', 'scanEvents']);
        Gate::forUser($actor)->authorize('evidence', $evidence->workItem);

        if ($evidence->latestScan()?->verdict !== MalwareScanVerdict::Clean) {
            throw new LockedHttpException('The document is quarantined and cannot be downloaded.');
        }

        if (! $this->storage->exists($evidence->logical_path)) {
            throw new GoneHttpException('The retained document is no longer available.');
        }

        $stream = $this->storage->readStream($evidence->logical_path);

        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);
            $sha256 = hash_final($hash);
        } finally {
            fclose($stream);
        }

        if ($bytes !== $evidence->bytes || ! hash_equals($evidence->sha256, $sha256)) {
            throw new ConflictHttpException('The retained document failed its integrity check.');
        }

        $this->recordAudit->handle(
            action: 'document_evidence.downloaded',
            actor: $actor,
            auditable: $evidence,
            after: [
                'work_item_id' => $evidence->work_item_id,
                'purpose' => $evidence->purpose->value,
                'sha256' => $evidence->sha256,
                'bytes' => $evidence->bytes,
            ],
        );

        return $evidence;
    }
}
