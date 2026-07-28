<?php

namespace App\Enums;

enum Permission: string
{
    case ManageFirmSettings = 'manage_firm_settings';
    case ManageMembers = 'manage_members';
    case InviteMembers = 'invite_members';
    case ManageClients = 'manage_clients';
    case ManageObligations = 'manage_obligations';
    case ViewAuditLog = 'view_audit_log';
    case ViewReports = 'view_reports';
    case AssignWork = 'assign_work';
    case PrepareWork = 'prepare_work';
    case ReviewWork = 'review_work';
    case ManageFilings = 'manage_filings';
    case ManagePayments = 'manage_payments';
    case ManageTaxRecords = 'manage_tax_records';
    case ManageImports = 'manage_imports';
    case ManageReadinessRules = 'manage_readiness_rules';
}
