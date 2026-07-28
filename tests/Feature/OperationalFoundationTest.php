<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use App\Support\OperationalHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationalFoundationTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_scheduler_heartbeat_command_records_current_time(): void
    {
        Date::setTestNow('2026-07-27 08:00:00 UTC');

        $this->artisan('platform:record-scheduler-heartbeat')
            ->expectsOutputToContain('Scheduler heartbeat recorded at')
            ->assertSuccessful();

        $this->assertSame(
            Date::now()->getTimestamp(),
            Cache::get(OperationalHealth::SCHEDULER_HEARTBEAT_KEY),
        );
    }

    public function test_queue_heartbeat_is_dispatched_to_the_platform_queue(): void
    {
        config(['platform.queue.name' => 'platform-operations']);
        Queue::fake();

        RecordQueueHeartbeat::dispatch();

        Queue::assertPushedOn('platform-operations', RecordQueueHeartbeat::class);
    }

    public function test_queue_heartbeat_job_records_processed_time(): void
    {
        Date::setTestNow('2026-07-27 08:01:00 UTC');

        app()->call([new RecordQueueHeartbeat, 'handle']);

        $this->assertSame(
            Date::now()->getTimestamp(),
            Cache::get(OperationalHealth::QUEUE_HEARTBEAT_KEY),
        );
    }

    public function test_operations_status_succeeds_when_both_heartbeats_are_fresh(): void
    {
        Date::setTestNow('2026-07-27 08:02:00 UTC');
        $health = app(OperationalHealth::class);
        $health->recordSchedulerHeartbeat();
        $health->recordQueueHeartbeat();

        $this->artisan('platform:operations-status')
            ->expectsOutputToContain('Platform scheduler and queue worker are healthy.')
            ->assertSuccessful();
    }

    public function test_operations_status_fails_when_a_heartbeat_is_missing(): void
    {
        app(OperationalHealth::class)->recordSchedulerHeartbeat();

        $this->artisan('platform:operations-status --json')
            ->expectsOutputToContain('"healthy":false')
            ->assertFailed();
    }

    public function test_operations_status_fails_when_heartbeats_are_stale(): void
    {
        config(['platform.operations.heartbeat_fresh_for_seconds' => 300]);
        Date::setTestNow('2026-07-27 08:00:00 UTC');
        $health = app(OperationalHealth::class);
        $health->recordSchedulerHeartbeat();
        $health->recordQueueHeartbeat();
        Date::setTestNow('2026-07-27 08:06:00 UTC');

        $this->artisan('platform:operations-status --json')
            ->expectsOutputToContain('"healthy":false')
            ->assertFailed();
    }

    public function test_platform_operational_schedules_are_registered(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('platform:record-scheduler-heartbeat')
            ->expectsOutputToContain('platform:queue-heartbeat')
            ->assertSuccessful();
    }

    public function test_database_queue_waits_for_committed_transactions(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame('platform', config('queue.connections.database.queue'));
    }
}
