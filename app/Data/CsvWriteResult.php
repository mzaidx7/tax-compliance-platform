<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CsvWriteResult
{
    public function __construct(
        public int $rowCount,
        public int $columnCount,
        public int $neutralizedCellCount,
        public int $bytes,
    ) {}
}
