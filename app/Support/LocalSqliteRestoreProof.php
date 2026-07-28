<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\RestoreProofResult;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class LocalSqliteRestoreProof
{
    /**
     * Tables whose contents form the critical-record manifest.
     *
     * @var list<string>
     */
    private const MANIFEST_TABLES = [
        'users',
        'firms',
        'firm_users',
        'clients',
        'obligations',
        'workflow_definitions',
        'workflow_steps',
        'work_items',
        'assignment_histories',
        'work_item_transitions',
        'checklist_templates',
        'checklist_versions',
        'checklist_items',
        'work_item_checklists',
        'checklist_item_completions',
        'firm_invitations',
        'audit_logs',
        'notifications',
        'notification_attempts',
    ];

    /**
     * Tables required before queue and scheduler processes can restart.
     *
     * @var list<string>
     */
    private const OPERATIONS_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    private const SYNTHETIC_PASSWORD = 'synthetic-restore-proof-password';

    /**
     * @throws FileNotFoundException
     */
    public function run(): RestoreProofResult
    {
        $startedAt = hrtime(true);
        $proofId = strtolower((string) Str::ulid());
        $basePath = storage_path('framework/testing');
        $paths = $this->artifactPaths($basePath, $proofId);
        $connections = $this->connectionNames($proofId);
        $originalConnections = config('database.connections');

        if (! is_array($originalConnections)) {
            throw new RuntimeException('Database connection configuration is invalid.');
        }

        File::ensureDirectoryExists($basePath);
        $this->assertArtifactsDoNotExist($paths);
        $this->configureConnections($originalConnections, $connections, $paths);

        $result = null;

        try {
            File::put($paths['source_database'], '');
            $this->migrate($connections['source']);
            $fixture = $this->seedSyntheticFixture($connections['source']);
            $sourceManifest = $this->manifest($connections['source']);

            File::put(
                $paths['source_file'],
                "Synthetic private restore proof\nFirm: {$fixture['firm_a_id']}\n",
            );

            DB::purge($connections['source']);

            $this->copyOrFail($paths['source_database'], $paths['backup_database']);
            $this->copyOrFail($paths['backup_database'], $paths['restored_database']);
            $this->copyOrFail($paths['source_file'], $paths['backup_file']);
            $this->copyOrFail($paths['backup_file'], $paths['restored_file']);

            $restoredManifest = $this->manifest($connections['restored']);
            $databaseIntegrityValid = $this->databaseIntegrityIsValid($connections['restored']);
            $foreignKeysValid = $this->foreignKeysAreValid($connections['restored']);
            $authenticationValid = $this->authenticationIsValid(
                $connections['restored'],
                $fixture['user_a_email'],
            );
            $tenantIsolationValid = $this->tenantIsolationIsValid(
                $connections['restored'],
                $fixture,
            );
            $operationsTablesPresent = $this->operationsTablesArePresent($connections['restored']);

            if ($sourceManifest !== $restoredManifest) {
                throw new RuntimeException('The restored database manifest does not match the source.');
            }

            $sourceFileChecksum = hash_file('sha256', $paths['source_file']);
            $restoredFileChecksum = hash_file('sha256', $paths['restored_file']);

            if ($sourceFileChecksum === false || $sourceFileChecksum !== $restoredFileChecksum) {
                throw new RuntimeException('The restored private file checksum does not match the source.');
            }

            if (! $databaseIntegrityValid
                || ! $foreignKeysValid
                || ! $authenticationValid
                || ! $tenantIsolationValid
                || ! $operationsTablesPresent) {
                throw new RuntimeException('One or more restored-environment acceptance checks failed.');
            }

            $result = new RestoreProofResult(
                proofId: $proofId,
                schemaVersion: $restoredManifest['schema_version'],
                migrationCount: $restoredManifest['migration_count'],
                recordCounts: $restoredManifest['record_counts'],
                databaseChecksum: $restoredManifest['checksum'],
                privateFileChecksum: $restoredFileChecksum,
                releaseChecksum: $this->releaseChecksum(),
                frameworkVersion: app()->version(),
                databaseIntegrityValid: $databaseIntegrityValid,
                foreignKeysValid: $foreignKeysValid,
                authenticationValid: $authenticationValid,
                tenantIsolationValid: $tenantIsolationValid,
                operationsTablesPresent: $operationsTablesPresent,
                artifactsCleaned: false,
                durationMilliseconds: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            );
        } finally {
            foreach ($connections as $connection) {
                DB::purge($connection);
            }

            config(['database.connections' => $originalConnections]);
            File::delete(array_values($paths));

            foreach ($paths as $path) {
                if (File::exists($path)) {
                    throw new RuntimeException('A temporary restore-proof artifact could not be removed.');
                }
            }
        }

        return $result->withArtifactsCleaned();
    }

    /**
     * @return array{
     *     source_database: string,
     *     backup_database: string,
     *     restored_database: string,
     *     source_file: string,
     *     backup_file: string,
     *     restored_file: string
     * }
     */
    private function artifactPaths(string $basePath, string $proofId): array
    {
        $prefix = $basePath.DIRECTORY_SEPARATOR."restore-proof-{$proofId}";

        return [
            'source_database' => "{$prefix}-source.sqlite",
            'backup_database' => "{$prefix}-backup.sqlite",
            'restored_database' => "{$prefix}-restored.sqlite",
            'source_file' => "{$prefix}-private-source.txt",
            'backup_file' => "{$prefix}-private-backup.txt",
            'restored_file' => "{$prefix}-private-restored.txt",
        ];
    }

    /**
     * @return array{source: string, backup: string, restored: string}
     */
    private function connectionNames(string $proofId): array
    {
        return [
            'source' => "restore_proof_source_{$proofId}",
            'backup' => "restore_proof_backup_{$proofId}",
            'restored' => "restore_proof_restored_{$proofId}",
        ];
    }

    /**
     * @param  array<string, mixed>  $originalConnections
     * @param  array{source: string, backup: string, restored: string}  $connections
     * @param  array<string, string>  $paths
     */
    private function configureConnections(
        array $originalConnections,
        array $connections,
        array $paths,
    ): void {
        $configured = $originalConnections;
        $configured[$connections['source']] = $this->sqliteConnection($paths['source_database']);
        $configured[$connections['backup']] = $this->sqliteConnection($paths['backup_database']);
        $configured[$connections['restored']] = $this->sqliteConnection($paths['restored_database']);

        config(['database.connections' => $configured]);
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function sqliteConnection(string $path): array
    {
        return [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'DELETE',
            'synchronous' => 'FULL',
            'transaction_mode' => 'IMMEDIATE',
        ];
    }

    private function migrate(string $connection): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('The isolated restore-proof migrations failed.');
        }
    }

    /**
     * @return array{firm_a_id: string, firm_b_id: string, user_a_id: int, user_a_email: string}
     */
    private function seedSyntheticFixture(string $connectionName): array
    {
        $connection = DB::connection($connectionName);
        $now = '2026-01-01 00:00:00';
        $firmAId = (string) Str::ulid();
        $firmBId = (string) Str::ulid();
        $membershipAId = (string) Str::ulid();
        $membershipBId = (string) Str::ulid();
        $auditAId = (string) Str::ulid();
        $auditBId = (string) Str::ulid();
        $notificationId = (string) Str::ulid();
        $attemptId = (string) Str::ulid();
        $clientAId = (string) Str::ulid();
        $clientBId = (string) Str::ulid();
        $obligationAId = (string) Str::ulid();
        $obligationBId = (string) Str::ulid();
        $workItemAId = (string) Str::ulid();
        $workItemBId = (string) Str::ulid();
        $workflowDefinitionAId = (string) Str::ulid();
        $workflowDefinitionBId = (string) Str::ulid();
        $checklistTemplateAId = (string) Str::ulid();
        $checklistTemplateBId = (string) Str::ulid();
        $checklistVersionAId = (string) Str::ulid();
        $checklistVersionBId = (string) Str::ulid();
        $checklistItemAId = (string) Str::ulid();
        $checklistItemBId = (string) Str::ulid();
        $workChecklistAId = (string) Str::ulid();
        $workChecklistBId = (string) Str::ulid();
        $correlationId = (string) Str::ulid();
        $userAEmail = 'owner.alpha@example.test';

        $connection->table('firms')->insert([
            [
                'id' => $firmAId,
                'name' => 'Synthetic Alpha Tax Practice',
                'slug' => 'synthetic-alpha',
                'timezone' => 'Asia/Dubai',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $firmBId,
                'name' => 'Synthetic Beta Tax Practice',
                'slug' => 'synthetic-beta',
                'timezone' => 'Asia/Dubai',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $userAId = (int) $connection->table('users')->insertGetId([
            'name' => 'Synthetic Alpha Owner',
            'email' => $userAEmail,
            'email_verified_at' => $now,
            'password' => Hash::make(self::SYNTHETIC_PASSWORD),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userBId = (int) $connection->table('users')->insertGetId([
            'name' => 'Synthetic Beta Owner',
            'email' => 'owner.beta@example.test',
            'email_verified_at' => $now,
            'password' => Hash::make(self::SYNTHETIC_PASSWORD),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $connection->table('firm_users')->insert([
            [
                'id' => $membershipAId,
                'firm_id' => $firmAId,
                'user_id' => $userAId,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $membershipBId,
                'firm_id' => $firmBId,
                'user_id' => $userBId,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $connection->table('workflow_definitions')->insert([
            ['id' => $workflowDefinitionAId, 'firm_id' => $firmAId, 'definition_key' => 'core-compliance-workflow', 'name' => 'Synthetic core workflow', 'version' => 1, 'status' => 'published', 'published_by' => $userAId, 'published_at' => $now, 'created_at' => $now],
            ['id' => $workflowDefinitionBId, 'firm_id' => $firmBId, 'definition_key' => 'core-compliance-workflow', 'name' => 'Synthetic core workflow', 'version' => 1, 'status' => 'published', 'published_by' => $userBId, 'published_at' => $now, 'created_at' => $now],
        ]);
        $connection->table('workflow_steps')->insert([
            ['id' => (string) Str::ulid(), 'firm_id' => $firmAId, 'workflow_definition_id' => $workflowDefinitionAId, 'from_status' => 'not_started', 'to_status' => 'documents_requested', 'assignment_role' => 'preparer', 'position' => 1, 'created_at' => $now],
            ['id' => (string) Str::ulid(), 'firm_id' => $firmBId, 'workflow_definition_id' => $workflowDefinitionBId, 'from_status' => 'not_started', 'to_status' => 'documents_requested', 'assignment_role' => 'preparer', 'position' => 1, 'created_at' => $now],
        ]);

        $connection->table('clients')->insert([
            [
                'id' => $clientAId,
                'firm_id' => $firmAId,
                'internal_code' => 'CL-RESTORE-001',
                'internal_code_normalized' => 'CL-RESTORE-001',
                'legal_name' => 'Synthetic Alpha Client LLC',
                'trade_name' => 'Synthetic Alpha Client',
                'entity_type' => 'Limited liability company',
                'status' => 'active',
                'created_by' => $userAId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $clientBId,
                'firm_id' => $firmBId,
                'internal_code' => 'CL-RESTORE-001',
                'internal_code_normalized' => 'CL-RESTORE-001',
                'legal_name' => 'Synthetic Beta Client FZ-LLC',
                'trade_name' => null,
                'entity_type' => 'Free zone company',
                'status' => 'active',
                'created_by' => $userBId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $connection->table('obligations')->insert([
            [
                'id' => $obligationAId,
                'firm_id' => $firmAId,
                'client_id' => $clientAId,
                'obligation_type' => 'Synthetic manual VAT review',
                'period_label' => 'Synthetic Q2 2026',
                'statutory_due_date' => '2026-09-28',
                'internal_target_date' => '2026-09-21',
                'origin' => 'manual',
                'status' => 'open',
                'source_reference' => 'Synthetic manual fixture, not regulatory guidance.',
                'last_verified_on' => '2026-07-27',
                'verified_by' => $userAId,
                'created_by' => $userAId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $obligationBId,
                'firm_id' => $firmBId,
                'client_id' => $clientBId,
                'obligation_type' => 'Synthetic manual licence review',
                'period_label' => null,
                'statutory_due_date' => '2026-10-15',
                'internal_target_date' => '2026-10-08',
                'origin' => 'manual',
                'status' => 'open',
                'source_reference' => 'Synthetic manual fixture, not regulatory guidance.',
                'last_verified_on' => '2026-07-27',
                'verified_by' => $userBId,
                'created_by' => $userBId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $connection->table('checklist_templates')->insert([
            ['id' => $checklistTemplateAId, 'firm_id' => $firmAId, 'template_key' => 'core-compliance-work', 'name' => 'Synthetic core checklist', 'created_by' => $userAId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $checklistTemplateBId, 'firm_id' => $firmBId, 'template_key' => 'core-compliance-work', 'name' => 'Synthetic core checklist', 'created_by' => $userBId, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('checklist_versions')->insert([
            ['id' => $checklistVersionAId, 'firm_id' => $firmAId, 'checklist_template_id' => $checklistTemplateAId, 'version' => 1, 'status' => 'published', 'published_by' => $userAId, 'published_at' => $now, 'created_at' => $now],
            ['id' => $checklistVersionBId, 'firm_id' => $firmBId, 'checklist_template_id' => $checklistTemplateBId, 'version' => 1, 'status' => 'published', 'published_by' => $userBId, 'published_at' => $now, 'created_at' => $now],
        ]);
        $connection->table('checklist_items')->insert([
            ['id' => $checklistItemAId, 'firm_id' => $firmAId, 'checklist_version_id' => $checklistVersionAId, 'item_key' => 'review-source', 'label' => 'Review synthetic source', 'position' => 1, 'required' => true, 'created_at' => $now],
            ['id' => $checklistItemBId, 'firm_id' => $firmBId, 'checklist_version_id' => $checklistVersionBId, 'item_key' => 'review-source', 'label' => 'Review synthetic source', 'position' => 1, 'required' => true, 'created_at' => $now],
        ]);

        $connection->table('work_items')->insert([
            [
                'id' => $workItemAId,
                'firm_id' => $firmAId,
                'obligation_id' => $obligationAId,
                'workflow_definition_id' => $workflowDefinitionAId,
                'status' => 'documents_requested',
                'created_by' => $userAId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $workItemBId,
                'firm_id' => $firmBId,
                'obligation_id' => $obligationBId,
                'workflow_definition_id' => $workflowDefinitionBId,
                'status' => 'documents_requested',
                'created_by' => $userBId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $connection->table('work_item_checklists')->insert([
            ['id' => $workChecklistAId, 'firm_id' => $firmAId, 'work_item_id' => $workItemAId, 'checklist_version_id' => $checklistVersionAId, 'applied_by' => $userAId, 'applied_at' => $now, 'created_at' => $now],
            ['id' => $workChecklistBId, 'firm_id' => $firmBId, 'work_item_id' => $workItemBId, 'checklist_version_id' => $checklistVersionBId, 'applied_by' => $userBId, 'applied_at' => $now, 'created_at' => $now],
        ]);
        $connection->table('checklist_item_completions')->insert([
            ['id' => (string) Str::ulid(), 'firm_id' => $firmAId, 'work_item_checklist_id' => $workChecklistAId, 'checklist_item_id' => $checklistItemAId, 'completed_by' => $userAId, 'evidence_note' => 'Synthetic restore evidence.', 'completed_at' => $now, 'created_at' => $now],
            ['id' => (string) Str::ulid(), 'firm_id' => $firmBId, 'work_item_checklist_id' => $workChecklistBId, 'checklist_item_id' => $checklistItemBId, 'completed_by' => $userBId, 'evidence_note' => 'Synthetic restore evidence.', 'completed_at' => $now, 'created_at' => $now],
        ]);

        $assignments = [];

        foreach ([
            [$firmAId, $workItemAId, $membershipAId, $userAId],
            [$firmBId, $workItemBId, $membershipBId, $userBId],
        ] as [$firmId, $workItemId, $membershipId, $userId]) {
            foreach (['preparer', 'reviewer', 'responsible_manager'] as $role) {
                $assignments[] = [
                    'id' => (string) Str::ulid(),
                    'firm_id' => $firmId,
                    'work_item_id' => $workItemId,
                    'assignment_role' => $role,
                    'assigned_membership_id' => $membershipId,
                    'assigned_by' => $userId,
                    'reason' => 'Synthetic restore proof assignment.',
                    'assigned_at' => $now,
                    'created_at' => $now,
                ];
            }
        }

        $connection->table('assignment_histories')->insert($assignments);

        $connection->table('work_item_transitions')->insert([
            [
                'id' => (string) Str::ulid(),
                'firm_id' => $firmAId,
                'work_item_id' => $workItemAId,
                'from_status' => 'not_started',
                'to_status' => 'documents_requested',
                'transitioned_by' => $userAId,
                'reason' => 'Synthetic restore proof transition.',
                'transitioned_at' => $now,
                'created_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'firm_id' => $firmBId,
                'work_item_id' => $workItemBId,
                'from_status' => 'not_started',
                'to_status' => 'documents_requested',
                'transitioned_by' => $userBId,
                'reason' => 'Synthetic restore proof transition.',
                'transitioned_at' => $now,
                'created_at' => $now,
            ],
        ]);

        $connection->table('audit_logs')->insert([
            [
                'id' => $auditAId,
                'firm_id' => $firmAId,
                'actor_type' => 'user',
                'actor_id' => (string) $userAId,
                'action' => 'restore_proof.alpha_created',
                'auditable_type' => 'firm',
                'auditable_id' => $firmAId,
                'after_values' => json_encode(['synthetic' => true], JSON_THROW_ON_ERROR),
                'reason' => 'Synthetic restore proof fixture',
                'correlation_id' => $correlationId,
                'created_at' => $now,
            ],
            [
                'id' => $auditBId,
                'firm_id' => $firmBId,
                'actor_type' => 'user',
                'actor_id' => (string) $userBId,
                'action' => 'restore_proof.beta_created',
                'auditable_type' => 'firm',
                'auditable_id' => $firmBId,
                'after_values' => json_encode(['synthetic' => true], JSON_THROW_ON_ERROR),
                'reason' => 'Synthetic restore proof fixture',
                'correlation_id' => (string) Str::ulid(),
                'created_at' => $now,
            ],
        ]);

        $connection->table('notifications')->insert([
            'id' => $notificationId,
            'firm_id' => $firmAId,
            'recipient_user_id' => $userAId,
            'template_key' => 'restore-proof',
            'template_version' => 1,
            'channel' => 'mail',
            'deterministic_key' => hash('sha256', "restore-proof|{$firmAId}"),
            'trigger_type' => 'restore_proof',
            'trigger_id' => $firmAId,
            'scheduled_at' => $now,
            'status' => 'delivered',
            'final_status' => 'delivered',
            'attempt_count' => 1,
            'correlation_id' => $correlationId,
            'completed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $connection->table('notification_attempts')->insert([
            'id' => $attemptId,
            'firm_id' => $firmAId,
            'notification_id' => $notificationId,
            'attempt_number' => 1,
            'status' => 'delivered',
            'provider_reference' => 'synthetic-provider-reference',
            'attempted_at' => $now,
            'created_at' => $now,
        ]);

        return [
            'firm_a_id' => $firmAId,
            'firm_b_id' => $firmBId,
            'user_a_id' => $userAId,
            'user_a_email' => $userAEmail,
        ];
    }

    /**
     * @return array{
     *     schema_version: string,
     *     migration_count: int,
     *     record_counts: array<string, int>,
     *     checksum: string
     * }
     */
    private function manifest(string $connectionName): array
    {
        $connection = DB::connection($connectionName);
        $records = [];
        $counts = [];

        foreach (self::MANIFEST_TABLES as $table) {
            $rows = $connection->table($table)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
            $records[$table] = $rows;
            $counts[$table] = count($rows);
        }

        $schemaVersion = $connection->table('migrations')
            ->orderByDesc('id')
            ->value('migration');

        if (! is_string($schemaVersion)) {
            throw new RuntimeException('The restored schema version could not be determined.');
        }

        $encodedRecords = json_encode($records, JSON_THROW_ON_ERROR);

        return [
            'schema_version' => $schemaVersion,
            'migration_count' => $connection->table('migrations')->count(),
            'record_counts' => $counts,
            'checksum' => hash('sha256', $encodedRecords),
        ];
    }

    private function databaseIntegrityIsValid(string $connectionName): bool
    {
        $row = DB::connection($connectionName)->selectOne('PRAGMA integrity_check');
        $values = $row === null ? [] : array_values((array) $row);

        return ($values[0] ?? null) === 'ok';
    }

    private function foreignKeysAreValid(string $connectionName): bool
    {
        return DB::connection($connectionName)->select('PRAGMA foreign_key_check') === [];
    }

    private function authenticationIsValid(string $connectionName, string $email): bool
    {
        $password = DB::connection($connectionName)
            ->table('users')
            ->where('email', $email)
            ->value('password');

        return is_string($password) && Hash::check(self::SYNTHETIC_PASSWORD, $password);
    }

    /**
     * @param  array{firm_a_id: string, firm_b_id: string, user_a_id: int, user_a_email: string}  $fixture
     */
    private function tenantIsolationIsValid(string $connectionName, array $fixture): bool
    {
        $connection = DB::connection($connectionName);

        $alphaMemberships = $connection->table('firm_users')
            ->where('firm_id', $fixture['firm_a_id'])
            ->where('user_id', $fixture['user_a_id'])
            ->count();
        $crossTenantMemberships = $connection->table('firm_users')
            ->where('firm_id', $fixture['firm_b_id'])
            ->where('user_id', $fixture['user_a_id'])
            ->count();
        $alphaAuditLogs = $connection->table('audit_logs')
            ->where('firm_id', $fixture['firm_a_id'])
            ->where('action', 'restore_proof.alpha_created')
            ->count();
        $crossTenantAuditLogs = $connection->table('audit_logs')
            ->where('firm_id', $fixture['firm_a_id'])
            ->where('action', 'restore_proof.beta_created')
            ->count();

        return $alphaMemberships === 1
            && $crossTenantMemberships === 0
            && $alphaAuditLogs === 1
            && $crossTenantAuditLogs === 0;
    }

    private function operationsTablesArePresent(string $connectionName): bool
    {
        foreach (self::OPERATIONS_TABLES as $table) {
            if (! Schema::connection($connectionName)->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $paths
     */
    private function assertArtifactsDoNotExist(array $paths): void
    {
        foreach ($paths as $path) {
            if (File::exists($path)) {
                throw new RuntimeException('A restore-proof artifact already exists.');
            }
        }
    }

    private function copyOrFail(string $source, string $destination): void
    {
        if (! File::copy($source, $destination)) {
            throw new RuntimeException('A restore-proof artifact could not be copied.');
        }
    }

    private function releaseChecksum(): string
    {
        $lockPath = base_path('composer.lock');
        $checksum = hash_file('sha256', $lockPath);

        if ($checksum === false) {
            throw new RuntimeException('The release dependency checksum could not be calculated.');
        }

        return $checksum;
    }
}
