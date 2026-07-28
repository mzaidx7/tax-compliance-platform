<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LocalSqliteRestoreProof;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('platform:prove-backup-restore {--synthetic-only : Confirm that only generated synthetic fixtures may be used}')]
#[Description('Run an isolated local SQLite backup and restore proof with synthetic data')]
final class ProveBackupRestore extends Command
{
    public function handle(LocalSqliteRestoreProof $proof): int
    {
        if (app()->environment('production')) {
            $this->components->error('This local proof is disabled in production.');

            return self::FAILURE;
        }

        if (! $this->option('synthetic-only')) {
            $this->components->error('Pass --synthetic-only to confirm that no application data may be used.');

            return self::FAILURE;
        }

        try {
            $result = $proof->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('The isolated backup and restore proof failed.');

            return self::FAILURE;
        }

        $this->components->info('The isolated synthetic backup and restore proof passed.');
        $this->components->twoColumnDetail('Schema version', $result->schemaVersion);
        $this->components->twoColumnDetail('Migrations', (string) $result->migrationCount);
        $this->components->twoColumnDetail('Database checksum', $result->databaseChecksum);
        $this->components->twoColumnDetail('Private-file checksum', $result->privateFileChecksum);
        $this->components->twoColumnDetail('Tenant isolation', $result->tenantIsolationValid ? 'passed' : 'failed');
        $this->components->twoColumnDetail('Authentication', $result->authenticationValid ? 'passed' : 'failed');
        $this->components->twoColumnDetail('Temporary artifacts', $result->artifactsCleaned ? 'removed' : 'present');
        $this->components->twoColumnDetail('Duration', "{$result->durationMilliseconds} ms");

        return $result->passed() ? self::SUCCESS : self::FAILURE;
    }
}
