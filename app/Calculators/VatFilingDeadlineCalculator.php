<?php

declare(strict_types=1);

namespace App\Calculators;

use App\Contracts\ObligationCalculator;
use App\ValueObjects\ObligationCalculationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class VatFilingDeadlineCalculator implements ObligationCalculator
{
    public function key(): string
    {
        return 'vat_filing_deadline_v1';
    }

    public function isRegulatory(): bool
    {
        return true;
    }

    public function acceptedInputs(): array
    {
        return ['tax_period_end'];
    }

    public function validateParameters(array $parameters): array
    {
        Validator::make(['parameters' => $parameters], ['parameters' => ['array', 'size:0']])->validate();

        return [];
    }

    public function calculate(array $inputs, array $parameters): ObligationCalculationResult
    {
        $this->validateParameters($parameters);

        /** @var array{tax_period_end: string} $validated */
        $validated = Validator::make($inputs, [
            'tax_period_end' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        $periodEnd = CarbonImmutable::createFromFormat('Y-m-d', $validated['tax_period_end'])->startOfDay();
        $dueDate = $periodEnd->addDays(28);

        if ($dueDate->isSaturday() || $dueDate->isSunday()) {
            $dueDate = $dueDate->nextWeekday();
        }

        return new ObligationCalculationResult(
            statutoryDueDate: $dueDate->toDateString(),
            explanation: 'The VAT Return deadline is calculated as 28 days after the Tax Period ends. If that date is a weekend, the next working day is used. Confirm any official FTA extension separately.',
        );
    }
}
