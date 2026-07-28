<?php

namespace Tests\Fixtures\Jobs;

use App\Jobs\FirmAwareJob;
use App\Jobs\Middleware\SetFirmContext;
use App\Models\FirmMembership;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class CountFirmMemberships implements FirmAwareJob, ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $firmId,
        private string $cacheKey,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new SetFirmContext];
    }

    public function handle(): void
    {
        Cache::put($this->cacheKey, FirmMembership::query()->count());
    }

    public function firmId(): string
    {
        return $this->firmId;
    }
}
