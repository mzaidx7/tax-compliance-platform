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
            self::TaxPeriods => 'Client tax periods',
            self::ExpiringDocuments => 'Expiring documents',
            self::WorkloadCompletion => 'Team tasks and completion',
        };
    }

    public function exportSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }
}
