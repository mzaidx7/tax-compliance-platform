<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Support\LocalSqliteRestoreProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class BackupRestoreProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_explicit_synthetic_only_confirmation(): void
    {
        $command = $this->artisan('platform:prove-backup-restore');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('Pass --synthetic-only')
            ->assertFailed();
    }

    public function test_command_is_disabled_in_production(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $command = $this->artisan('platform:prove-backup-restore', ['--synthetic-only' => true]);
            $this->assertInstanceOf(PendingCommand::class, $command);

            $command
                ->expectsOutputToContain('disabled in production')
                ->assertFailed();
            $command->run();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_it_proves_an_isolated_synthetic_backup_and_restore(): void
    {
        DB::table('cache')->insert([
            'key' => 'restore-proof-default-database-sentinel',
            'value' => 'untouched',
            'expiration' => 4_102_444_800,
        ]);

        $result = app(LocalSqliteRestoreProof::class)->run();

        $this->assertTrue($result->passed());
        $this->assertTrue($result->databaseIntegrityValid);
        $this->assertTrue($result->foreignKeysValid);
        $this->assertTrue($result->authenticationValid);
        $this->assertTrue($result->tenantIsolationValid);
        $this->assertTrue($result->operationsTablesPresent);
        $this->assertTrue($result->artifactsCleaned);
        $this->assertGreaterThan(0, $result->migrationCount);
        $this->assertNotSame('', $result->schemaVersion);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->databaseChecksum);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->privateFileChecksum);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->releaseChecksum);
        $this->assertSame(2, $result->recordCounts['users']);
        $this->assertSame(2, $result->recordCounts['firms']);
        $this->assertSame(2, $result->recordCounts['firm_users']);
        $this->assertSame(2, $result->recordCounts['clients']);
        $this->assertSame(2, $result->recordCounts['obligations']);
        $this->assertSame(2, $result->recordCounts['workflow_definitions']);
        $this->assertSame(2, $result->recordCounts['workflow_steps']);
        $this->assertSame(2, $result->recordCounts['work_items']);
        $this->assertSame(6, $result->recordCounts['assignment_histories']);
        $this->assertSame(2, $result->recordCounts['work_item_transitions']);
        $this->assertSame(2, $result->recordCounts['checklist_templates']);
        $this->assertSame(2, $result->recordCounts['checklist_versions']);
        $this->assertSame(2, $result->recordCounts['checklist_items']);
        $this->assertSame(2, $result->recordCounts['work_item_checklists']);
        $this->assertSame(2, $result->recordCounts['checklist_item_completions']);
        $this->assertSame(2, $result->recordCounts['audit_logs']);
        $this->assertSame(1, $result->recordCounts['notifications']);
        $this->assertSame(1, $result->recordCounts['notification_attempts']);
        $this->assertSame(
            'untouched',
            DB::table('cache')->where('key', 'restore-proof-default-database-sentinel')->value('value'),
        );
        $this->assertSame(
            [],
            File::glob(storage_path("framework/testing/restore-proof-{$result->proofId}-*")),
        );
    }

    public function test_command_reports_a_successful_proof(): void
    {
        $command = $this->artisan('platform:prove-backup-restore', ['--synthetic-only' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('restore proof passed')
            ->expectsOutputToContain('Temporary artifacts')
            ->assertSuccessful();
    }
}
