<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Documents\GenerateDocumentExpiryReminders;
use App\Jobs\Middleware\SetFirmContext;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantCache;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class RecordFirmScheduledWorkHeartbeat implements FirmAwareJob, ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(
        private readonly string $firmId,
        public readonly CarbonImmutable $scheduledFor,
    ) {
        if ($firmId === '') {
            throw new InvalidArgumentException('Firm scheduled work requires a firm identity.');
        }

        $this->onQueue((string) config('platform.queue.name', 'platform'));
        $this->afterCommit();
    }

    public function firmId(): string
    {
        return $this->firmId;
    }

    public function generationKey(): string
    {
        return hash('sha256', "firm-scheduled-work:v1:{$this->firmId}:{$this->scheduledFor->utc()->format('Y-m-d\TH:i')}");
    }

    public function correlationId(): string
    {
        return 'scheduled-work:'.substr($this->generationKey(), 0, 24);
    }

    public function uniqueId(): string
    {
        return $this->generationKey();
    }

    public function heartbeatCacheKey(): string
    {
        return 'operations.scheduled-work.'.$this->generationKey();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SetFirmContext];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        TenantCache $cache,
        FirmContext $firmContext,
        GenerateDocumentExpiryReminders $generateDocumentExpiryReminders,
    ): void {
        $firm = $firmContext->firm();
        $remindersGenerated = $generateDocumentExpiryReminders->handle(
            $this->scheduledFor->setTimezone($firm->timezone),
        );

        $cache->put(
            $this->heartbeatCacheKey(),
            [
                'firm_id' => $this->firmId,
                'scheduled_for' => $this->scheduledFor->utc()->toIso8601String(),
                'processed_at' => Date::now()->utc()->toIso8601String(),
                'generation_key' => $this->generationKey(),
                'correlation_id' => $this->correlationId(),
                'document_expiry_reminders_generated' => $remindersGenerated,
            ],
            max(60, (int) config('platform.operations.scheduled_work_heartbeat_ttl_seconds', 86400)),
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Firm scheduled-work heartbeat failed.', [
            'firm_id' => $this->firmId,
            'generation_key' => $this->generationKey(),
            'correlation_id' => $this->correlationId(),
            'exception_class' => $exception::class,
        ]);
    }
}
