<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Actions\Audit\RecordAudit;
use App\Data\ExportArtifact;
use App\Exports\SpreadsheetSafeCsv;
use App\Models\User;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class CreateCsvExport
{
    public function __construct(
        private SpreadsheetSafeCsv $csv,
        private TenantStorage $storage,
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $rows
     */
    public function handle(
        string $name,
        array $headers,
        iterable $rows,
        ?User $actor = null,
    ): ExportArtifact {
        $this->firmContext->firm();
        $this->assertActorMatchesContext($actor);
        $fileName = $this->fileName($name);
        $logicalPath = "exports/{$fileName}";
        $stream = fopen(
            'php://temp/maxmemory:'.$this->temporaryMemoryBytes(),
            'w+b',
        );

        if ($stream === false) {
            throw new RuntimeException('The temporary CSV stream could not be opened.');
        }

        try {
            $result = $this->csv->write($stream, $headers, $rows);

            if (rewind($stream) === false) {
                throw new RuntimeException('The CSV stream could not be rewound.');
            }

            $hashContext = hash_init('sha256');
            hash_update_stream($hashContext, $stream);
            $sha256 = hash_final($hashContext);

            if (rewind($stream) === false) {
                throw new RuntimeException('The CSV stream could not be prepared for storage.');
            }

            $storedPath = $this->storage->writeStream($logicalPath, $stream);
            $createdAt = Date::now()->toImmutable();

            try {
                $this->recordAudit->handle(
                    action: 'firm.export.created',
                    actor: $actor,
                    after: [
                        'file_name' => $fileName,
                        'stored_path' => $storedPath,
                        'sha256' => $sha256,
                        'bytes' => $result->bytes,
                        'row_count' => $result->rowCount,
                        'column_count' => $result->columnCount,
                        'neutralized_cell_count' => $result->neutralizedCellCount,
                    ],
                );
            } catch (Throwable $exception) {
                if (! $this->storage->delete($logicalPath)) {
                    throw new RuntimeException(
                        'The export audit failed and the stored artifact could not be removed.',
                        previous: $exception,
                    );
                }

                throw $exception;
            }

            return new ExportArtifact(
                fileName: $fileName,
                logicalPath: $logicalPath,
                storedPath: $storedPath,
                sha256: $sha256,
                bytes: $result->bytes,
                rowCount: $result->rowCount,
                columnCount: $result->columnCount,
                neutralizedCellCount: $result->neutralizedCellCount,
                createdAt: $createdAt,
            );
        } finally {
            fclose($stream);
        }
    }

    private function assertActorMatchesContext(?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $membership = $this->firmContext->membership();

        if ($membership === null || $membership->user_id !== $actor->getKey()) {
            throw new AuthorizationException('The export actor does not match the active firm membership.');
        }
    }

    private function fileName(string $name): string
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]{0,79}\z/', $name) !== 1) {
            throw new InvalidArgumentException('Export names must use a bounded lowercase slug.');
        }

        return "{$name}-".Str::lower((string) Str::ulid()).'.csv';
    }

    private function temporaryMemoryBytes(): int
    {
        $bytes = config('platform.exports.temporary_memory_bytes');

        if (! is_int($bytes) || $bytes < 1) {
            throw new RuntimeException('The CSV temporary memory limit is not configured correctly.');
        }

        return $bytes;
    }
}
