<?php

declare(strict_types=1);

$firmIds = static fn (string $value): array => array_values(array_filter(
    array_map('trim', explode(',', $value)),
    static fn (string $firmId): bool => $firmId !== '',
));

return [
    'features' => [
        'client_master' => [
            'enabled' => (bool) env('FEATURE_CLIENT_MASTER', false),
            'firm_ids' => $firmIds((string) env('FEATURE_CLIENT_MASTER_FIRM_IDS', '')),
        ],
        'compliance_operations' => [
            'enabled' => (bool) env('FEATURE_COMPLIANCE_OPERATIONS', false),
            'firm_ids' => $firmIds((string) env('FEATURE_COMPLIANCE_OPERATIONS_FIRM_IDS', '')),
        ],
        'imports' => [
            'enabled' => (bool) env('FEATURE_IMPORTS', false),
            'firm_ids' => $firmIds((string) env('FEATURE_IMPORTS_FIRM_IDS', '')),
        ],
        'e_invoicing_readiness' => [
            'enabled' => (bool) env('FEATURE_EINVOICING_READINESS', false),
            'firm_ids' => $firmIds((string) env('FEATURE_EINVOICING_READINESS_FIRM_IDS', '')),
        ],
        'audit_viewer' => [
            'enabled' => (bool) env('FEATURE_AUDIT_VIEWER', false),
            'firm_ids' => $firmIds((string) env('FEATURE_AUDIT_VIEWER_FIRM_IDS', '')),
        ],
    ],

    'queue' => [
        'name' => env('PLATFORM_QUEUE', 'platform'),
    ],

    'storage' => [
        'disk' => env('TENANT_FILESYSTEM_DISK', 'tenant-private'),
    ],

    'exports' => [
        'max_rows' => (int) env('EXPORT_MAX_ROWS', 100000),
        'max_columns' => (int) env('EXPORT_MAX_COLUMNS', 100),
        'max_cell_characters' => (int) env('EXPORT_MAX_CELL_CHARACTERS', 32767),
        'max_bytes' => (int) env('EXPORT_MAX_BYTES', 52428800),
        'temporary_memory_bytes' => (int) env('EXPORT_TEMPORARY_MEMORY_BYTES', 1048576),
    ],

    'operations' => [
        'heartbeat_fresh_for_seconds' => (int) env('PLATFORM_HEARTBEAT_FRESH_FOR_SECONDS', 300),
        'scheduled_firm_batch_size' => (int) env('PLATFORM_SCHEDULED_FIRM_BATCH_SIZE', 100),
        'scheduled_work_heartbeat_ttl_seconds' => (int) env('PLATFORM_SCHEDULED_WORK_HEARTBEAT_TTL_SECONDS', 86400),
    ],
];
