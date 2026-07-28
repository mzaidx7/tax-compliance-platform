<?php

declare(strict_types=1);

namespace App\Enums;

enum Feature: string
{
    case ClientMaster = 'client_master';
    case ComplianceOperations = 'compliance_operations';
    case Imports = 'imports';
    case EInvoicingReadiness = 'e_invoicing_readiness';
    case AuditViewer = 'audit_viewer';

    public function label(): string
    {
        return match ($this) {
            self::ClientMaster => 'Client master',
            self::ComplianceOperations => 'Compliance operations',
            self::Imports => 'Imports',
            self::EInvoicingReadiness => 'E-invoicing readiness',
            self::AuditViewer => 'Audit register',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ClientMaster => 'The canonical client identity register for this firm.',
            self::ComplianceOperations => 'Obligations, work items, filings and payments for this firm.',
            self::Imports => 'Bulk import and reconciliation tooling for this firm.',
            self::EInvoicingReadiness => 'E-invoicing data-quality readiness checks for this firm.',
            self::AuditViewer => 'The read-only audit register for this firm.',
        };
    }
}
