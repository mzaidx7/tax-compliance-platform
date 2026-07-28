<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Actions\Exports\CreateCsvExport;
use App\Data\AuditRegisterFilters;
use App\Data\ExportArtifact;
use App\Enums\Feature;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Export the filtered audit register as a CSV artifact.
 *
 * The export is a read of retained evidence. It creates no audit record other
 * than its own download record, and it never edits or removes the records it
 * exports.
 */
final readonly class ExportAuditRegister
{
    private const HEADERS = [
        'recorded_at_utc',
        'action',
        'actor_id',
        'auditable_type',
        'auditable_id',
        'reason',
        'correlation_id',
    ];

    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CreateCsvExport $createCsvExport,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, AuditRegisterFilters $filters): ExportArtifact
    {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::AuditViewer, $firmId)) {
            throw new AuthorizationException('The audit viewer is not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('viewAny', AuditLog::class);

        $artifact = $this->createCsvExport->handle(
            name: 'audit-register',
            headers: self::HEADERS,
            rows: $this->rows($filters),
            actor: $actor,
        );

        $this->recordAudit->handle(
            action: 'audit_register.exported',
            actor: $actor,
            after: [
                'file_name' => $artifact->fileName,
                'sha256' => $artifact->sha256,
                'row_count' => $artifact->rowCount,
                'bytes' => $artifact->bytes,
                'filters' => $filters->toAuditMetadata(),
            ],
        );

        return $artifact;
    }

    /**
     * Stream matching records in chunks so a large register never loads at once.
     *
     * @return Generator<int, list<string|int|float|bool|null>>
     */
    private function rows(AuditRegisterFilters $filters): Generator
    {
        $query = $filters->apply(AuditLog::query())
            ->orderBy('created_at')
            ->orderBy('id');

        foreach ($query->lazy(500) as $record) {
            yield [
                $record->created_at->utc()->toDateTimeString(),
                $record->action,
                $record->actor_id,
                $record->auditable_type === null ? null : class_basename($record->auditable_type),
                $record->auditable_id,
                $record->reason,
                $record->correlation_id,
            ];
        }
    }
}
