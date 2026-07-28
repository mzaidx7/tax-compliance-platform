<?php

declare(strict_types=1);

namespace App\Calculators;

use App\Contracts\ObligationCalculator;
use App\ValueObjects\ObligationCalculationResult;
use Illuminate\Support\Facades\Validator;

final class ManualDatePassthroughCalculator implements ObligationCalculator
{
    public function key(): string
    {
        return 'manual_date_passthrough';
    }

    public function acceptedInputs(): array
    {
        return ['statutory_due_date'];
    }

    public function validateParameters(array $parameters): array
    {
        Validator::make(['parameters' => $parameters], [
            'parameters' => ['array', 'size:0'],
        ])->validate();

        return [];
    }

    public function calculate(array $inputs, array $parameters): ObligationCalculationResult
    {
        $this->validateParameters($parameters);
        /** @var array{statutory_due_date: string} $validated */
        $validated = Validator::make($inputs, [
            'statutory_due_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return new ObligationCalculationResult(
            statutoryDueDate: $validated['statutory_due_date'],
            explanation: 'The statutory due date was supplied by an authorised user. No statutory calculation was performed.',
        );
    }
}
