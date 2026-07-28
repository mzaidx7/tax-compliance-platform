<?php

namespace App\Jobs\Middleware;

use App\Enums\FirmStatus;
use App\Jobs\FirmAwareJob;
use App\Models\Firm;
use App\Tenancy\FirmContext;
use Closure;
use LogicException;

class SetFirmContext
{
    /**
     * Process the queued job.
     *
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        if (! $job instanceof FirmAwareJob) {
            throw new LogicException('Firm context middleware requires a firm-aware job.');
        }

        $firm = Firm::query()
            ->whereKey($job->firmId())
            ->where('status', FirmStatus::Active)
            ->firstOrFail();

        app(FirmContext::class)->runForFirm(
            $firm,
            fn () => $next($job),
        );
    }
}
