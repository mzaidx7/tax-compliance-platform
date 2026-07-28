<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\DispatchFirmNotification;
use App\Enums\NotificationAttemptStatus;
use App\Enums\NotificationFinalStatus;
use App\Enums\NotificationRequestStatus;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\NotificationAttempt;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Notifications\FirmAccessSummaryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class NotificationDeliveryLifecycleTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_request_is_persisted_once_and_duplicate_delivery_is_suppressed(): void
    {
        Date::setTestNow('2026-07-27 12:30:00 UTC');
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);
        $dispatcher = app(DispatchFirmNotification::class);

        $first = $dispatcher->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'daily-access-summary:2026-07-27',
            $recipient,
        );
        $duplicate = $dispatcher->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'daily-access-summary:2026-07-27',
            $recipient,
        );

        $this->assertSame($first->id, $duplicate->id);
        $this->assertDatabaseCount('notifications', 1);
        Notification::assertSentToTimes($recipient, FirmAccessSummaryNotification::class, 1);

        $request = NotificationRequest::query()->sole();

        $this->assertSame($firm->id, $request->firm_id);
        $this->assertSame($recipient->id, $request->recipient_user_id);
        $this->assertSame('firm_access_summary', $request->template_key);
        $this->assertSame(1, $request->template_version);
        $this->assertSame('mail', $request->channel->value);
        $this->assertSame(FirmMembership::class, $request->trigger_type);
        $this->assertSame($membership->id, $request->trigger_id);
        $this->assertSame(NotificationRequestStatus::Queued, $request->status);
        $this->assertNull($request->final_status);
        $this->assertSame(0, $request->attempt_count);
        $this->assertSame(64, strlen($request->deterministic_key));
        $this->assertSame(2, AuditLog::query()->count());
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'firm.notification.duplicate_suppressed')->count(),
        );
        $this->assertStringNotContainsString(
            'daily-access-summary:2026-07-27',
            json_encode($request->getAttributes(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_invalid_idempotency_key_is_rejected_before_persistence(): void
    {
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(DispatchFirmNotification::class)->handle(
                $recipient,
                new FirmAccessSummaryNotification($firm->id, $recipient->id),
                '../unsafe key',
            );
        } finally {
            $this->assertDatabaseCount('notifications', 0);
            Notification::assertNothingSent();
        }
    }

    public function test_future_scheduled_request_applies_the_same_queue_delay(): void
    {
        Date::setTestNow('2026-07-27 12:32:00 UTC');
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);
        $scheduledAt = CarbonImmutable::parse('2026-07-27 13:00:00 UTC');

        $request = app(DispatchFirmNotification::class)->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'scheduled-access-summary:2026-07-27T13:00',
            scheduledAt: $scheduledAt,
        );

        $this->assertTrue($request->scheduled_at->shiftTimezone('UTC')->equalTo($scheduledAt));
        Notification::assertSentTo(
            $recipient,
            FirmAccessSummaryNotification::class,
            fn (FirmAccessSummaryNotification $sent): bool => $sent->delay instanceof CarbonImmutable
                && $sent->delay->equalTo($scheduledAt),
        );
    }

    public function test_successful_delivery_records_one_immutable_attempt_and_final_status(): void
    {
        Date::setTestNow('2026-07-27 12:35:00 UTC');
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);
        $notification = new FirmAccessSummaryNotification($firm->id, $recipient->id);
        $request = app(DispatchFirmNotification::class)->handle(
            $recipient,
            $notification,
            'delivery-success:2026-07-27T12:35',
        );
        $job = new SendQueuedNotifications($recipient, $notification, ['mail']);
        $middleware = $notification->middleware($recipient, 'mail')[0];
        $deliveryCalls = 0;

        $middleware->handle($job, function () use (&$deliveryCalls): void {
            $deliveryCalls++;
        });
        $middleware->handle($job, function () use (&$deliveryCalls): void {
            $deliveryCalls++;
        });

        $request->refresh();
        $attempt = NotificationAttempt::query()->sole();

        $this->assertSame(1, $deliveryCalls);
        $this->assertSame(NotificationRequestStatus::Delivered, $request->status);
        $this->assertSame(NotificationFinalStatus::Delivered, $request->final_status);
        $this->assertSame(1, $request->attempt_count);
        $this->assertNotNull($request->completed_at);
        $this->assertSame($request->id, $attempt->notification_id);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(NotificationAttemptStatus::Delivered, $attempt->status);
        $this->assertNull($attempt->provider_reference);
        $this->assertNull($attempt->failure_reason);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'firm.notification.delivered')->count(),
        );

        $this->expectException(LogicException::class);
        $attempt->update(['provider_reference' => 'cannot-be-added-later']);
    }

    public function test_failed_attempt_uses_a_safe_reason_and_terminal_failure_is_recorded(): void
    {
        Date::setTestNow('2026-07-27 12:40:00 UTC');
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);
        $notification = new FirmAccessSummaryNotification($firm->id, $recipient->id);
        $request = app(DispatchFirmNotification::class)->handle(
            $recipient,
            $notification,
            'delivery-failure:2026-07-27T12:40',
        );
        $job = new SendQueuedNotifications($recipient, $notification, ['mail']);
        $middleware = $notification->middleware($recipient, 'mail')[0];
        $exception = new RuntimeException('Synthetic sensitive provider response');

        try {
            $middleware->handle($job, static function () use ($exception): never {
                throw $exception;
            });
            $this->fail('The synthetic delivery should fail.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $request->refresh();
        $attempt = NotificationAttempt::query()->sole();

        $this->assertSame(NotificationRequestStatus::RetryPending, $request->status);
        $this->assertNull($request->final_status);
        $this->assertSame(1, $request->attempt_count);
        $this->assertSame(NotificationAttemptStatus::Failed, $attempt->status);
        $this->assertSame('exception:runtime_exception', $attempt->failure_reason);
        $this->assertStringNotContainsString('provider response', $attempt->failure_reason);

        $notification->failed($exception);
        $request->refresh();

        $this->assertSame(NotificationRequestStatus::Failed, $request->status);
        $this->assertSame(NotificationFinalStatus::Failed, $request->final_status);
        $this->assertNotNull($request->completed_at);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'firm.notification.failed')->count(),
        );
    }

    public function test_request_identity_and_attempt_history_cannot_be_rewritten_or_deleted(): void
    {
        Notification::fake();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($membership);
        $request = app(DispatchFirmNotification::class)->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'immutable-request:2026-07-27',
        );

        try {
            $request->update(['template_version' => 2]);
            $this->fail('Notification request identity should be immutable.');
        } catch (LogicException) {
            $this->assertSame(1, $request->refresh()->template_version);
        }

        $this->expectException(LogicException::class);
        $request->delete();
    }

    public function test_cross_firm_requests_are_hidden_and_attempt_relationship_is_constrained(): void
    {
        Notification::fake();
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $firmA = Firm::factory()->create();
        $firmB = Firm::factory()->create();
        $membershipA = $this->createFirmMembership($firmA, $recipientA);
        $membershipB = $this->createFirmMembership($firmB, $recipientB);
        $this->activateFirmMembership($membershipA);
        $requestA = app(DispatchFirmNotification::class)->handle(
            $recipientA,
            new FirmAccessSummaryNotification($firmA->id, $recipientA->id),
            'same-business-trigger',
        );
        $this->activateFirmMembership($membershipB);
        $requestB = app(DispatchFirmNotification::class)->handle(
            $recipientB,
            new FirmAccessSummaryNotification($firmB->id, $recipientB->id),
            'same-business-trigger',
        );

        $this->assertNotSame($requestA->deterministic_key, $requestB->deterministic_key);
        $this->assertNull(NotificationRequest::query()->find($requestA->id));
        $this->assertSame($requestB->id, NotificationRequest::query()->sole()->id);

        $this->expectException(QueryException::class);

        NotificationAttempt::query()->create([
            'notification_id' => $requestA->id,
            'attempt_number' => 1,
            'status' => NotificationAttemptStatus::Failed,
            'failure_reason' => 'cross_firm_rejected',
            'attempted_at' => Date::now(),
        ]);
    }

    public function test_untracked_notification_cannot_enter_queue_middleware(): void
    {
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $this->createFirmMembership($firm, $recipient);
        $notification = new FirmAccessSummaryNotification($firm->id, $recipient->id);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tracked dispatcher');

        $notification->middleware($recipient, 'mail');
    }
}
