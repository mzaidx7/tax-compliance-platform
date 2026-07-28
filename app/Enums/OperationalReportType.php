<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalReportType: string
{
    case MonthlySchedule = 'monthly_schedule';
    case TaxPeriods = 'tax_periods';
    case ExpiringDocuments = 'expiring_documents';
    case WorkloadCompletion = 'workload_completion';

    public function label(): string
    {
        return match ($this) {
            self::MonthlySchedule => 'Monthly schedule',
            self::TaxPeriods => 'Tax-period list',
            self::ExpiringDocuments => 'Expiring documents',
            self::WorkloadCompletion => 'Workload and completion',
        };
    }

    public function exportSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }
}
