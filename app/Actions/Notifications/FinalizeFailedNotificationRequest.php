<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmStatus;
use App\Enums\NotificationFinalStatus;
use App\Enums\NotificationRequestStatus;
use App\Models\Firm;
use App\Models\NotificationRequest;
use App\Tenancy\FirmContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class FinalizeFailedNotificationRequest
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        string $firmId,
        string $notificationRequestId,
        Throwable $exception,
    ): void {
        $firm = Firm::query()
            ->whereKey($firmId)
            ->where('status', FirmStatus::Active)
            ->first();

        if ($firm === null) {
            Log::error('Notification failure could not be finalised for an inactive or missing firm.', [
                'firm_id' => $firmId,
                'notification_request_id' => $notificationRequestId,
                'exception_class' => $exception::class,
            ]);

            return;
        }

        $this->firmContext->runForFirm($firm, function () use (
            $exception,
            $notificationRequestId,
        ): void {
            DB::transaction(function () use ($exception, $notificationRequestId): void {
                $request = NotificationRequest::query()
                    ->whereKey($notificationRequestId)
                    ->lockForUpdate()
                    ->first();

                if ($request === null || $request->final_status !== null) {
                    return;
                }

                $failureReason = 'exception:'.Str::snake(class_basename($exception));

                $request->update([
                    'status' => NotificationRequestStatus::Failed,
                    'final_status' => NotificationFinalStatus::Failed,
                    'completed_at' => Date::now(),
                ]);

                $this->recordAudit->handle(
                    action: 'firm.notification.failed',
                    auditable: $request,
                    after: [
                        'notification_request_id' => $request->getKey(),
                        'attempt_count' => $request->attempt_count,
                        'channel' => $request->channel->value,
                        'failure_reason' => $failureReason,
                    ],
                    correlationId: $request->correlation_id,
                );
            });
        });
    }
}
