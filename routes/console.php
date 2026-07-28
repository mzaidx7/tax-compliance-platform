<?php

use App\Console\Commands\DispatchFirmScheduledWork;
use App\Console\Commands\RecordSchedulerHeartbeat;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RecordSchedulerHeartbeat::class)
    ->name('platform:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::job(
    new RecordQueueHeartbeat,
    (string) config('platform.queue.name', 'platform'),
    (string) config('queue.default', 'database'),
)
    ->name('platform:queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command(DispatchFirmScheduledWork::class)
    ->name('platform:firm-scheduled-work')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
