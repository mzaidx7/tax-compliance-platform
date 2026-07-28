<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Jobs\RecordFirmScheduledWorkHeartbeat;
use App\Models\Firm;
use App\Models\User;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmScheduledWorkTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_command_dispatches_one_job_for_each_active_firm_only(): void
    {
        Date::setTestNow('2026-07-27 09:12:34 UTC');
        config([
            'platform.operations.scheduled_firm_batch_size' => 1,
            'platform.queue.name' => 'platform-operations',
        ]);
        $activeFirmA = Firm::factory()->create();
        $activeFirmB = Firm::factory()->create();
        $suspendedFirm = Firm::factory()->suspended()->create();
        Queue::fake();

        $this->artisan('platform:dispatch-firm-scheduled-work')
            ->expectsOutputToContain('Dispatched 2 firm scheduled-work heartbeat job(s)')
            ->assertSuccessful();

        Queue::assertPushed(RecordFirmScheduledWorkHeartbeat::class, 2);
        Queue::assertPushedOn('platform-operations', RecordFirmScheduledWorkHeartbeat::class);
        Queue::assertPushed(
            RecordFirmScheduledWorkHeartbeat::class,
            fn (RecordFirmScheduledWorkHeartbeat $job): bool => $job->firmId() === $activeFirmA->id
                && $job->scheduledFor->equalTo(CarbonImmutable::parse('2026-07-27 09:12:00 UTC'))
                && $job->afterCommit === true,
        );
        Queue::assertPushed(
            RecordFirmScheduledWorkHeartbeat::class,
            fn (RecordFirmScheduledWorkHeartbeat $job): bool => $job->firmId() === $activeFirmB->id,
        );
        Queue::assertNotPushed(
            RecordFirmScheduledWorkHeartbeat::class,
            fn (RecordFirmScheduledWorkHeartbeat $job): bool => $job->firmId() === $suspendedFirm->id,
        );
    }

    public function test_job_contract_is_encrypted_unique_bounded_and_deterministic(): void
    {
        $scheduledFor = CarbonImmutable::parse('2026-07-27 09:15:00 UTC');
        $job = new RecordFirmScheduledWorkHeartbeat('firm-a', $scheduledFor);
        $sameSlot = new RecordFirmScheduledWorkHeartbeat('firm-a', $scheduledFor);
        $nextSlot = new RecordFirmScheduledWorkHeartbeat('firm-a', $scheduledFor->addMinutes(5));

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame($job->generationKey(), $sameSlot->generationKey());
        $this->assertSame($job->uniqueId(), $sameSlot->uniqueId());
        $this->assertSame($job->correlationId(), $sameSlot->correlationId());
        $this->assertNotSame($job->uniqueId(), $nextSlot->uniqueId());
        $this->assertSame([10, 30, 60], $job->backoff());
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame(600, $job->uniqueFor);
    }

    public function test_laravel_unique_lock_rejects_the_same_firm_and_generation_slot(): void
    {
        $scheduledFor = CarbonImmutable::parse('2026-07-27 09:15:00 UTC');
        $first = new RecordFirmScheduledWorkHeartbeat('firm-a', $scheduledFor);
        $duplicate = new RecordFirmScheduledWorkHeartbeat('firm-a', $scheduledFor);
        $otherFirm = new RecordFirmScheduledWorkHeartbeat('firm-b', $scheduledFor);
        $lock = new UniqueLock(Cache::store());

        try {
            $this->assertTrue($lock->acquire($first));
            $this->assertFalse($lock->acquire($duplicate));
            $this->assertTrue($lock->acquire($otherFirm));
        } finally {
            $lock->release($first);
            $lock->release($otherFirm);
        }
    }

    public function test_job_rejects_a_missing_firm_identity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecordFirmScheduledWorkHeartbeat('', CarbonImmutable::now());
    }

    public function test_job_writes_only_to_its_firm_cache_and_restores_previous_context(): void
    {
        Date::setTestNow('2026-07-27 09:16:00 UTC');
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $membershipB = $this->createFirmMembership($firmB, User::factory()->create());
        $this->activateFirmMembership($membershipB);
        $job = new RecordFirmScheduledWorkHeartbeat(
            $firmA->id,
            CarbonImmutable::parse('2026-07-27 09:15:00 UTC'),
        );

        Bus::dispatchSync($job);

        $firmACacheKey = "tenant:testing:firm:{$firmA->id}:{$job->heartbeatCacheKey()}";
        $firmBCacheKey = "tenant:testing:firm:{$firmB->id}:{$job->heartbeatCacheKey()}";
        $payload = Cache::get($firmACacheKey);

        $this->assertIsArray($payload);
        $this->assertSame($firmA->id, $payload['firm_id']);
        $this->assertSame('2026-07-27T09:15:00+00:00', $payload['scheduled_for']);
        $this->assertSame('2026-07-27T09:16:00+00:00', $payload['processed_at']);
        $this->assertSame($job->generationKey(), $payload['generation_key']);
        $this->assertSame($job->correlationId(), $payload['correlation_id']);
        $this->assertFalse(Cache::has($firmBCacheKey));
        $this->assertSame($firmB->id, app(FirmContext::class)->firmId());
        $this->assertSame($membershipB->id, app(FirmContext::class)->membership()?->id);
    }

    public function test_job_fails_closed_if_the_firm_is_suspended_before_processing(): void
    {
        $firm = Firm::factory()->create();
        $job = new RecordFirmScheduledWorkHeartbeat(
            $firm->id,
            CarbonImmutable::parse('2026-07-27 09:20:00 UTC'),
        );
        $firm->update(['status' => 'suspended', 'suspended_at' => now()]);

        try {
            Bus::dispatchSync($job);
            $this->fail('The suspended firm job should not run.');
        } catch (ModelNotFoundException) {
            $this->assertFalse(
                Cache::has("tenant:testing:firm:{$firm->id}:{$job->heartbeatCacheKey()}"),
            );
            $this->assertFalse(app(FirmContext::class)->hasFirm());
        }
    }

    public function test_same_generation_can_be_safely_rerun_without_creating_another_cache_record(): void
    {
        $firm = Firm::factory()->create();
        $job = new RecordFirmScheduledWorkHeartbeat(
            $firm->id,
            CarbonImmutable::parse('2026-07-27 09:25:00 UTC'),
        );

        Bus::dispatchSync($job);
        Bus::dispatchSync($job);

        $expectedKey = "tenant:testing:firm:{$firm->id}:{$job->heartbeatCacheKey()}";

        $this->assertTrue(Cache::has($expectedKey));
        $this->assertSame($job->generationKey(), Cache::get($expectedKey)['generation_key']);
    }

    public function test_firm_scheduled_work_is_registered_with_overlap_and_single_server_locks(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('platform:dispatch-firm-scheduled-work')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(
                fn (object $event): bool => str_contains(
                    (string) $event->command,
                    'platform:dispatch-firm-scheduled-work',
                ),
            );

        $this->assertNotNull($event);
        $this->assertSame('platform:firm-scheduled-work', $event->description);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
