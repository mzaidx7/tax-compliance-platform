<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Contracts\ObligationCalculator;
use App\ValueObjects\ObligationCalculationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class RegulatoryTestCalculator implements ObligationCalculator
{
    public function key(): string
    {
        return 'synthetic_regulatory_test';
    }

    public function isRegulatory(): bool
    {
        return true;
    }

    public function acceptedInputs(): array
    {
        return ['period_end'];
    }

    public function validateParameters(array $parameters): array
    {
        /** @var array{days: int} $validated */
        $validated = Validator::make($parameters, ['days' => ['required', 'integer', 'min:0', 'max:365']])->validate();

        return $validated;
    }

    public function calculate(array $inputs, array $parameters): ObligationCalculationResult
    {
        /** @var array{period_end: string} $validated */
        $validated = Validator::make($inputs, ['period_end' => ['required', 'date_format:Y-m-d']])->validate();
        $parameters = $this->validateParameters($parameters);
        $date = CarbonImmutable::createFromFormat('Y-m-d', $validated['period_end'])->addDays($parameters['days']);

        return new ObligationCalculationResult($date->toDateString(), 'Synthetic test calculation, not regulatory guidance.');
    }
}
