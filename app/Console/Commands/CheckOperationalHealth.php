<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OperationalHealth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:operations-status {--json : Return machine-readable JSON}')]
#[Description('Check the most recent scheduler and queue heartbeats')]
final class CheckOperationalHealth extends Command
{
    public function handle(OperationalHealth $health): int
    {
        $schedulerAt = $health->schedulerLastSeenAt();
        $queueAt = $health->queueLastProcessedAt();
        $schedulerFresh = $health->schedulerIsFresh();
        $queueFresh = $health->queueIsFresh();
        $healthy = $schedulerFresh && $queueFresh;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'healthy' => $healthy,
                'scheduler' => [
                    'fresh' => $schedulerFresh,
                    'last_seen_at' => $schedulerAt?->toIso8601String(),
                ],
                'queue' => [
                    'fresh' => $queueFresh,
                    'last_processed_at' => $queueAt?->toIso8601String(),
                ],
            ], JSON_THROW_ON_ERROR));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->components->twoColumnDetail(
            'Scheduler',
            $this->statusLabel($schedulerFresh, $schedulerAt),
        );
        $this->components->twoColumnDetail(
            'Queue worker',
            $this->statusLabel($queueFresh, $queueAt),
        );

        if (! $healthy) {
            $this->components->error('Platform operations are unhealthy or have not reported yet.');

            return self::FAILURE;
        }

        $this->components->info('Platform scheduler and queue worker are healthy.');

        return self::SUCCESS;
    }

    private function statusLabel(bool $fresh, ?CarbonImmutable $recordedAt): string
    {
        $state = $fresh ? '<fg=green>fresh</>' : '<fg=red>stale</>';
        $timestamp = $recordedAt?->toIso8601String() ?? 'never';

        return "{$state} ({$timestamp})";
    }
}
