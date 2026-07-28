<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AssignmentHistory;
use App\Models\Client;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\WorkItem;
use Database\Seeders\ComplianceMvpAcceptanceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('platform:seed-compliance-acceptance {--synthetic-only : Confirm that only generated synthetic fixture data may be created}')]
#[Description('Seed the deterministic 200-client compliance MVP acceptance fixture into a clean non-production database')]
final class SeedComplianceMvpAcceptance extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('The synthetic acceptance fixture is disabled in production.');

            return self::FAILURE;
        }

        if (! $this->option('synthetic-only')) {
            $this->components->error('Pass --synthetic-only to confirm that no operational data may be used.');

            return self::FAILURE;
        }

        if (Firm::query()->exists()) {
            $this->components->error('The synthetic acceptance fixture requires a clean database.');

            return self::FAILURE;
        }

        $startedAt = hrtime(true);

        try {
            DB::transaction(static fn () => app(ComplianceMvpAcceptanceSeeder::class)->run(), 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('The synthetic acceptance fixture failed and was rolled back.');

            return self::FAILURE;
        }

        $duration = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $this->components->info('The synthetic compliance MVP acceptance fixture is ready.');
        $this->components->twoColumnDetail('Firms', (string) Firm::query()->count());
        $this->components->twoColumnDetail('Clients', (string) Client::withoutGlobalScopes()->count());
        $this->components->twoColumnDetail('Obligations', (string) Obligation::withoutGlobalScopes()->count());
        $this->components->twoColumnDetail('Work items', (string) WorkItem::withoutGlobalScopes()->count());
        $this->components->twoColumnDetail('Assignments', (string) AssignmentHistory::withoutGlobalScopes()->count());
        $this->components->twoColumnDetail('Fixture duration', "{$duration} ms");
        $this->line('This local measurement is not a production performance claim.');

        return self::SUCCESS;
    }
}
