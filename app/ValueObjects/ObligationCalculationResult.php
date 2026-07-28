<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class ObligationCalculationResult
{
    public function __construct(
        public string $statutoryDueDate,
        public string $explanation,
    ) {}
}
