<?php

declare(strict_types=1);

namespace Tests\Unit\Calculators;

use App\Calculators\VatFilingDeadlineCalculator;
use Tests\TestCase;

final class VatFilingDeadlineCalculatorTest extends TestCase
{
    public function test_it_calculates_twenty_eight_days_after_the_period_end(): void
    {
        $result = (new VatFilingDeadlineCalculator)->calculate(['tax_period_end' => '2026-11-30'], []);

        self::assertSame('2026-12-28', $result->statutoryDueDate);
    }

    public function test_it_moves_a_weekend_deadline_to_the_next_weekday(): void
    {
        $result = (new VatFilingDeadlineCalculator)->calculate(['tax_period_end' => '2026-01-31'], []);

        self::assertSame('2026-03-02', $result->statutoryDueDate);
    }
}
