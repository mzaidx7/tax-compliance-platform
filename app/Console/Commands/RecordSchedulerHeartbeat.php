<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OperationalHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:record-scheduler-heartbeat')]
#[Description('Record proof that the platform scheduler is running')]
final class RecordSchedulerHeartbeat extends Command
{
    public function handle(OperationalHealth $health): int
    {
        $recordedAt = $health->recordSchedulerHeartbeat();

        $this->components->info("Scheduler heartbeat recorded at {$recordedAt->toIso8601String()}.");

        return self::SUCCESS;
    }
}
