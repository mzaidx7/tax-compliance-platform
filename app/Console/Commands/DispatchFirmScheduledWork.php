<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FirmStatus;
use App\Jobs\RecordFirmScheduledWorkHeartbeat;
use App\Models\Firm;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('platform:dispatch-firm-scheduled-work')]
#[Description('Dispatch one isolated scheduled-work heartbeat for each active firm')]
final class DispatchFirmScheduledWork extends Command
{
    public function handle(): int
    {
        $scheduledFor = CarbonImmutable::now('UTC')->startOfMinute();
        $dispatched = 0;

        Firm::query()
            ->select('id')
            ->where('status', FirmStatus::Active)
            ->chunkById(
                max(1, (int) config('platform.operations.scheduled_firm_batch_size', 100)),
                function (Collection $firms) use ($scheduledFor, &$dispatched): void {
                    foreach ($firms as $firm) {
                        RecordFirmScheduledWorkHeartbeat::dispatch(
                            (string) $firm->getKey(),
                            $scheduledFor,
                        );

                        $dispatched++;
                    }
                },
            );

        $this->components->info(
            "Dispatched {$dispatched} firm scheduled-work heartbeat job(s) for {$scheduledFor->toIso8601String()}.",
        );

        return self::SUCCESS;
    }
}
