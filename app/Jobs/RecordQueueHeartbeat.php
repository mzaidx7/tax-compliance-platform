<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\OperationalHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue((string) config('platform.queue.name', 'platform'));
        $this->afterCommit();
    }

    public function handle(OperationalHealth $health): void
    {
        $health->recordQueueHeartbeat();
    }
}
