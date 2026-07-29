<?php

declare(strict_types=1);

namespace App\Calculators;

use App\Contracts\ObligationCalculator;
use App\ValueObjects\ObligationCalculationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class CorporateTaxFilingDeadlineCalculator implements ObligationCalculator
{
    public function key(): string
    {
        return 'corporate_tax_filing_deadline_v1';
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
        $dueDate = $periodEnd->addMonthsNoOverflow(9)->endOfMonth();

        return new ObligationCalculationResult(
            statutoryDueDate: $dueDate->toDateString(),
            explanation: 'The Corporate Tax Return and payment deadline is calculated as nine months after the end of the Tax Period. Confirm unusual first Tax Periods and official extensions separately.',
        );
    }
}
