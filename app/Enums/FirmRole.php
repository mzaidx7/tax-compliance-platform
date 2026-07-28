<?php

namespace App\Enums;

enum FirmRole: string
{
    case FirmAdministrator = 'firm_administrator';
    case Manager = 'manager';
    case Preparer = 'preparer';
    case Reviewer = 'reviewer';
    case DataCleanupOperator = 'data_cleanup_operator';
    case ReadOnly = 'read_only';

    public function label(): string
    {
        return match ($this) {
            self::FirmAdministrator => 'Firm administrator',
            self::Manager => 'Manager',
            self::Preparer => 'Preparer',
            self::Reviewer => 'Reviewer',
            self::DataCleanupOperator => 'Data cleanup operator',
            self::ReadOnly => 'Read only',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::FirmAdministrator => Permission::cases(),
            self::Manager => [
                Permission::ViewAuditLog,
                Permission::ViewReports,
                Permission::AssignWork,
                Permission::ManageObligations,
                Permission::PrepareWork,
                Permission::ReviewWork,
                Permission::ManageFilings,
                Permission::ManagePayments,
                Permission::ManageTaxRecords,
                Permission::ManageReadinessRules,
            ],
            self::Preparer => [Permission::PrepareWork],
            self::Reviewer => [Permission::PrepareWork, Permission::ReviewWork],
            self::DataCleanupOperator => [Permission::ManageImports, Permission::ManageReadinessRules],
            self::ReadOnly => [Permission::ViewReports],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
