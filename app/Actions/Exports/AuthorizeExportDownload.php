<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Actions\Audit\RecordAudit;
use App\Data\DownloadableExport;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AuthorizeExportDownload
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private TenantStorage $storage,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, AuditLog $exportAuditLog): DownloadableExport
    {
        $firmId = $this->firmContext->firm()->id;

        if ($exportAuditLog->action !== 'firm.export.created') {
            throw new NotFoundHttpException('The requested export artifact does not exist.');
        }

        $metadata = $exportAuditLog->after_values ?? [];
        $fileName = $metadata['file_name'] ?? null;
        $logicalPath = $metadata['logical_path'] ?? null;
        $storedPath = $metadata['stored_path'] ?? null;
        $sha256 = $metadata['sha256'] ?? null;
        $bytes = $metadata['bytes'] ?? null;

        if (! is_string($fileName) || preg_match('/\A[a-z0-9][a-z0-9-]{0,79}-[0-9a-z]{26}\.csv\z/', $fileName) !== 1) {
            throw new NotFoundHttpException('The export file name is invalid.');
        }
        if (! $this->canDownloadOperationalReport($actor, $exportAuditLog, $fileName)) {
            if (! $this->featureFlags->enabled(Feature::AuditViewer, $firmId)) {
                throw new AuthorizationException('The audit viewer is not enabled for this firm.');
            }
            Gate::forUser($actor)->authorize('view', $exportAuditLog);
        }

        $expectedLogicalPath = "exports/{$fileName}";
        $logicalPath = is_string($logicalPath) ? $logicalPath : $expectedLogicalPath;

        if (
            $logicalPath !== $expectedLogicalPath
            || ! is_string($storedPath)
            || ! hash_equals($this->storage->path($logicalPath), $storedPath)
            || ! is_string($sha256)
            || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1
            || ! is_int($bytes)
            || $bytes < 0
        ) {
            throw new NotFoundHttpException('The export metadata is invalid.');
        }

        if (! $this->storage->exists($logicalPath)) {
            throw new GoneHttpException('The retained export artifact is no longer available.');
        }

        [$actualSha256, $actualBytes] = $this->hashArtifact($logicalPath);

        if (! hash_equals($sha256, $actualSha256) || $bytes !== $actualBytes) {
            throw new ConflictHttpException('The retained export artifact failed its integrity check.');
        }

        $this->recordAudit->handle(
            action: 'firm.export.downloaded',
            actor: $actor,
            auditable: $exportAuditLog,
            after: [
                'export_audit_log_id' => $exportAuditLog->id,
                'file_name' => $fileName,
                'sha256' => $sha256,
                'bytes' => $bytes,
            ],
        );

        return new DownloadableExport($fileName, $logicalPath, $sha256, $bytes);
    }

    /**
     * @return array{string, int}
     */
    private function hashArtifact(string $logicalPath): array
    {
        $stream = $this->storage->readStream($logicalPath);

        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);

            return [hash_final($hash), $bytes];
        } finally {
            fclose($stream);
        }
    }

    private function canDownloadOperationalReport(User $actor, AuditLog $exportAuditLog, string $fileName): bool
    {
        if (
            preg_match('/\Aclient-master-[0-9a-z]{26}\.csv\z/', $fileName) === 1
            && $exportAuditLog->actor_type === $actor->getMorphClass()
            && (string) $exportAuditLog->actor_id === (string) $actor->id
        ) {
            return true;
        }

        $isOperationalReport = preg_match(
            '/\A(monthly-schedule|tax-periods|expiring-documents|workload-completion|client-master)-/',
            $fileName,
        ) === 1;
        $membership = $this->firmContext->membership();

        return $isOperationalReport
            && $membership?->user_id === $actor->id
            && $membership->hasPermission(Permission::ViewReports)
            && $exportAuditLog->actor_type === $actor->getMorphClass()
            && (string) $exportAuditLog->actor_id === (string) $actor->id;
    }
}
