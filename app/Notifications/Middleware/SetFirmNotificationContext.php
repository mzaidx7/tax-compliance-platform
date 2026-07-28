<?php

declare(strict_types=1);

namespace App\Notifications\Middleware;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmStatus;
use App\Enums\NotificationAttemptStatus;
use App\Enums\NotificationFinalStatus;
use App\Enums\NotificationRequestStatus;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Notifications\FirmNotification;
use App\Tenancy\FirmContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final readonly class SetFirmNotificationContext
{
    public function __construct(
        private string $firmId,
        private int $recipientUserId,
        private string $notificationRequestId,
        private string $channel,
    ) {}

    /**
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        if (! $job instanceof SendQueuedNotifications) {
            throw new LogicException('Firm notification middleware requires a queued notification job.');
        }

        $notification = $job->notification;
        $recipient = $job->notifiables->sole();

        if (
            ! $notification instanceof FirmNotification
            || $notification->firmId() !== $this->firmId
            || $notification->recipientUserId() !== $this->recipientUserId
            || $notification->notificationRequestId() !== $this->notificationRequestId
            || ! $recipient instanceof User
            || $recipient->getKey() !== $this->recipientUserId
        ) {
            throw new AuthorizationException('The queued firm notification identity is invalid.');
        }

        $firm = Firm::query()
            ->whereKey($this->firmId)
            ->where('status', FirmStatus::Active)
            ->firstOrFail();

        app(FirmContext::class)->runForFirm($firm, function () use ($job, $next, $notification): void {
            $request = NotificationRequest::query()
                ->whereKey($this->notificationRequestId)
                ->sole();

            $this->assertRequestIdentity($request, $notification);

            if ($request->final_status !== null) {
                return;
            }

            $request->update(['status' => NotificationRequestStatus::Processing]);

            try {
                $recipientIsActive = FirmMembership::query()
                    ->where('user_id', $this->recipientUserId)
                    ->where('status', FirmMembershipStatus::Active)
                    ->exists();

                if (! $recipientIsActive) {
                    throw new AuthorizationException('The firm notification recipient no longer has active access.');
                }

                $next($job);
                $this->recordAttempt($request, NotificationAttemptStatus::Delivered);
            } catch (Throwable $exception) {
                $this->recordAttempt(
                    $request,
                    NotificationAttemptStatus::Failed,
                    'exception:'.Str::snake(class_basename($exception)),
                );

                throw $exception;
            }
        });
    }

    private function assertRequestIdentity(
        NotificationRequest $request,
        FirmNotification $notification,
    ): void {
        if (
            $request->firm_id !== $this->firmId
            || $request->recipient_user_id !== $this->recipientUserId
            || $request->template_key !== $notification->templateKey()
            || $request->template_version !== $notification->templateVersion()
            || $request->channel->value !== $this->channel
        ) {
            throw new AuthorizationException('The queued notification request identity is invalid.');
        }
    }

    private function recordAttempt(
        NotificationRequest $request,
        NotificationAttemptStatus $status,
        ?string $failureReason = null,
    ): void {
        DB::transaction(function () use ($failureReason, $request, $status): void {
            $lockedRequest = NotificationRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->sole();

            if ($lockedRequest->final_status !== null) {
                return;
            }

            $attemptNumber = $lockedRequest->attempt_count + 1;

            $lockedRequest->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => $status,
                'provider_reference' => null,
                'failure_reason' => $failureReason,
                'attempted_at' => Date::now(),
            ]);

            if ($status === NotificationAttemptStatus::Delivered) {
                $lockedRequest->update([
                    'status' => NotificationRequestStatus::Delivered,
                    'final_status' => NotificationFinalStatus::Delivered,
                    'attempt_count' => $attemptNumber,
                    'completed_at' => Date::now(),
                ]);

                app(RecordAudit::class)->handle(
                    action: 'firm.notification.delivered',
                    auditable: $lockedRequest,
                    after: [
                        'notification_request_id' => $lockedRequest->getKey(),
                        'attempt_number' => $attemptNumber,
                        'channel' => $lockedRequest->channel->value,
                    ],
                    correlationId: $lockedRequest->correlation_id,
                );

                return;
            }

            $lockedRequest->update([
                'status' => NotificationRequestStatus::RetryPending,
                'attempt_count' => $attemptNumber,
            ]);
        });
    }
}
