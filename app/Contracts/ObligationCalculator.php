<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\ObligationCalculationResult;

interface ObligationCalculator
{
    public function key(): string;

    public function isRegulatory(): bool;

    /** @return list<string> */
    public function acceptedInputs(): array;

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function validateParameters(array $parameters): array;

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $parameters
     */
    public function calculate(array $inputs, array $parameters): ObligationCalculationResult;
}
