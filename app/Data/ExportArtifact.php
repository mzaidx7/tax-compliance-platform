<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ExportArtifact
{
    public function __construct(
        public string $auditLogId,
        public string $fileName,
        public string $logicalPath,
        public string $storedPath,
        public string $sha256,
        public int $bytes,
        public int $rowCount,
        public int $columnCount,
        public int $neutralizedCellCount,
        public CarbonImmutable $createdAt,
    ) {}
}
