<?php

declare(strict_types=1);

namespace App\Data;

final readonly class DownloadableExport
{
    public function __construct(
        public string $fileName,
        public string $logicalPath,
        public string $sha256,
        public int $bytes,
    ) {}
}
