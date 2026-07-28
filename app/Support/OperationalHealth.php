<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

final class OperationalHealth
{
    public const string QUEUE_HEARTBEAT_KEY = 'platform:operations:queue:last_processed_at';

    public const string SCHEDULER_HEARTBEAT_KEY = 'platform:operations:scheduler:last_seen_at';

    public function recordQueueHeartbeat(): CarbonImmutable
    {
        return $this->record(self::QUEUE_HEARTBEAT_KEY);
    }

    public function recordSchedulerHeartbeat(): CarbonImmutable
    {
        return $this->record(self::SCHEDULER_HEARTBEAT_KEY);
    }

    public function queueLastProcessedAt(): ?CarbonImmutable
    {
        return $this->read(self::QUEUE_HEARTBEAT_KEY);
    }

    public function schedulerLastSeenAt(): ?CarbonImmutable
    {
        return $this->read(self::SCHEDULER_HEARTBEAT_KEY);
    }

    public function queueIsFresh(): bool
    {
        return $this->isFresh($this->queueLastProcessedAt());
    }

    public function schedulerIsFresh(): bool
    {
        return $this->isFresh($this->schedulerLastSeenAt());
    }

    private function record(string $key): CarbonImmutable
    {
        $recordedAt = Date::now()->toImmutable();

        Cache::forever($key, $recordedAt->getTimestamp());

        return $recordedAt;
    }

    private function read(string $key): ?CarbonImmutable
    {
        $timestamp = Cache::get($key);

        if (! is_int($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC($timestamp);
    }

    private function isFresh(?CarbonImmutable $recordedAt): bool
    {
        if ($recordedAt === null) {
            return false;
        }

        $now = Date::now()->toImmutable();
        $freshForSeconds = max(1, (int) config('platform.operations.heartbeat_fresh_for_seconds', 300));

        return $recordedAt->lessThanOrEqualTo($now)
            && $recordedAt->greaterThanOrEqualTo($now->subSeconds($freshForSeconds));
    }
}
