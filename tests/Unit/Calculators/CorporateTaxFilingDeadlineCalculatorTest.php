<?php

declare(strict_types=1);

namespace Tests\Unit\Calculators;

use App\Calculators\CorporateTaxFilingDeadlineCalculator;
use Tests\TestCase;

final class CorporateTaxFilingDeadlineCalculatorTest extends TestCase
{
    public function test_it_calculates_nine_months_after_the_tax_period_end(): void
    {
        $result = (new CorporateTaxFilingDeadlineCalculator)->calculate(['tax_period_end' => '2025-12-31'], []);

        self::assertSame('2026-09-30', $result->statutoryDueDate);
    }

    public function test_it_handles_a_non_standard_first_tax_period(): void
    {
        $result = (new CorporateTaxFilingDeadlineCalculator)->calculate(['tax_period_end' => '2026-08-31'], []);

        self::assertSame('2027-05-31', $result->statutoryDueDate);
    }
}
