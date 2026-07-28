<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\DispatchFirmNotification;
use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Notifications\FirmAccessSummaryNotification;
use App\Notifications\FirmInvitationNotification;
use App\Notifications\Middleware\SetFirmNotificationContext;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\Concerns\BuildsFirmTenancy;
use Tests\TestCase;

class FirmNotificationIsolationTest extends TestCase
{
    use BuildsFirmTenancy;
    use RefreshDatabase;

    public function test_active_member_notification_is_encrypted_queued_and_audited_without_content(): void
    {
        Notification::fake();
        $actor = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership(
            $firm,
            $actor,
            FirmRole::FirmAdministrator,
        );
        $this->activateFirmMembership($membership);
        $notification = new FirmAccessSummaryNotification($firm->id, $actor->id);

        app(DispatchFirmNotification::class)->handle(
            $actor,
            $notification,
            'access-summary:synthetic-active-member',
            $actor,
        );

        Notification::assertSentTo(
            $actor,
            FirmAccessSummaryNotification::class,
            fn (FirmAccessSummaryNotification $sent): bool => $sent->firmId() === $firm->id
                && $sent->recipientUserId() === $actor->id,
        );
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertSame('platform', $notification->queue);
        $this->assertTrue($notification->afterCommit);

        $audit = AuditLog::query()->where('action', 'firm.notification.queued')->sole();

        $this->assertSame($firm->id, $audit->firm_id);
        $this->assertSame((string) $actor->id, $audit->actor_id);
        $this->assertSame($actor->id, $audit->after_values['recipient_user_id']);
        $this->assertSame('firm_access_summary', $audit->after_values['template_key']);
        $this->assertSame(1, $audit->after_values['template_version']);
        $this->assertSame('mail', $audit->after_values['channel']);
        $this->assertSame(
            NotificationRequest::query()->sole()->id,
            $audit->after_values['notification_request_id'],
        );
        $this->assertStringNotContainsString(
            $firm->name,
            json_encode($audit->after_values, JSON_THROW_ON_ERROR),
        );
    }

    public function test_recipient_from_another_firm_is_rejected(): void
    {
        Notification::fake();
        $actor = User::factory()->create();
        $firmA = Firm::factory()->create();
        $membershipA = $this->createFirmMembership(
            $firmA,
            $actor,
            FirmRole::FirmAdministrator,
        );
        $recipientB = User::factory()->create();
        $firmB = Firm::factory()->create();
        $this->createFirmMembership($firmB, $recipientB);
        $this->activateFirmMembership($membershipA);

        $this->expectException(AuthorizationException::class);

        try {
            app(DispatchFirmNotification::class)->handle(
                $recipientB,
                new FirmAccessSummaryNotification($firmA->id, $recipientB->id),
                'access-summary:foreign-recipient',
                $actor,
            );
        } finally {
            Notification::assertNothingSent();
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_inactive_recipient_is_rejected(): void
    {
        Notification::fake();
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $actorMembership = $this->createFirmMembership(
            $firm,
            $actor,
            FirmRole::FirmAdministrator,
        );
        $this->createFirmMembership(
            $firm,
            $recipient,
            FirmRole::Preparer,
            FirmMembershipStatus::Suspended,
        );
        $this->activateFirmMembership($actorMembership);

        $this->expectException(AuthorizationException::class);

        app(DispatchFirmNotification::class)->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'access-summary:inactive-recipient',
            $actor,
        );
    }

    public function test_notification_identity_must_match_active_firm_and_recipient(): void
    {
        Notification::fake();
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $otherFirm = Firm::factory()->create();
        $actorMembership = $this->createFirmMembership(
            $firm,
            $actor,
            FirmRole::FirmAdministrator,
        );
        $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($actorMembership);

        $this->expectException(AuthorizationException::class);

        app(DispatchFirmNotification::class)->handle(
            $recipient,
            new FirmAccessSummaryNotification($otherFirm->id, $recipient->id),
            'access-summary:mismatched-firm',
            $actor,
        );
    }

    public function test_actor_must_match_active_membership(): void
    {
        Notification::fake();
        $actor = User::factory()->create();
        $otherActor = User::factory()->create();
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $actorMembership = $this->createFirmMembership(
            $firm,
            $actor,
            FirmRole::FirmAdministrator,
        );
        $this->createFirmMembership($firm, $recipient);
        $this->activateFirmMembership($actorMembership);

        $this->expectException(AuthorizationException::class);

        app(DispatchFirmNotification::class)->handle(
            $recipient,
            new FirmAccessSummaryNotification($firm->id, $recipient->id),
            'access-summary:mismatched-actor',
            $otherActor,
        );
    }

    public function test_queue_middleware_restores_context_and_renders_only_the_target_firm(): void
    {
        $recipient = User::factory()->create(['name' => 'Synthetic Recipient']);
        $firmA = Firm::factory()->create(['name' => 'Synthetic Firm Alpha']);
        $firmB = Firm::factory()->create(['name' => 'Synthetic Firm Beta']);
        $this->createFirmMembership($firmA, $recipient, FirmRole::Reviewer);
        $this->createFirmMembership($firmB, User::factory()->create());
        $notification = new FirmAccessSummaryNotification($firmA->id, $recipient->id);
        Notification::fake();
        app(FirmContext::class)->runForFirm(
            $firmA,
            fn () => app(DispatchFirmNotification::class)->handle(
                $recipient,
                $notification,
                'access-summary:middleware-rendering',
            ),
        );
        $job = new SendQueuedNotifications($recipient, $notification, ['mail']);
        $middleware = $notification->middleware($recipient, 'mail')[0];

        $this->assertInstanceOf(SetFirmNotificationContext::class, $middleware);
        $this->assertFalse(app(FirmContext::class)->hasFirm());

        $middleware->handle($job, function () use ($firmA, $firmB, $notification, $recipient): void {
            $this->assertSame($firmA->id, app(FirmContext::class)->firmId());
            $this->assertSame(1, FirmMembership::query()->count());
            $mail = $notification->toMail($recipient);
            $rendered = json_encode([
                'subject' => $mail->subject,
                'lines' => $mail->introLines,
            ], JSON_THROW_ON_ERROR);

            $this->assertStringContainsString($firmA->name, $rendered);
            $this->assertStringContainsString('Reviewer', $rendered);
            $this->assertStringNotContainsString($firmB->name, $rendered);
        });

        $this->assertFalse(app(FirmContext::class)->hasFirm());
    }

    public function test_queue_middleware_rechecks_membership_before_delivery(): void
    {
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $membership = $this->createFirmMembership($firm, $recipient);
        $notification = new FirmAccessSummaryNotification($firm->id, $recipient->id);
        Notification::fake();
        app(FirmContext::class)->runForFirm(
            $firm,
            fn () => app(DispatchFirmNotification::class)->handle(
                $recipient,
                $notification,
                'access-summary:membership-recheck',
            ),
        );
        $job = new SendQueuedNotifications($recipient, $notification, ['mail']);
        $middleware = $notification->middleware($recipient, 'mail')[0];

        app(FirmContext::class)->runForFirm(
            $firm,
            fn () => $membership->update([
                'status' => FirmMembershipStatus::Suspended,
                'suspended_at' => now(),
            ]),
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('no longer has active access');

        $middleware->handle($job, fn () => $this->fail('An inactive recipient reached notification delivery.'));
    }

    public function test_notification_refuses_immediate_rendering_without_firm_context(): void
    {
        $recipient = User::factory()->create();
        $firm = Firm::factory()->create();
        $this->createFirmMembership($firm, $recipient);
        $notification = new FirmAccessSummaryNotification($firm->id, $recipient->id);

        $this->assertFalse($notification->shouldSend($recipient, 'mail'));
    }

    public function test_middleware_rejects_non_notification_jobs(): void
    {
        $this->expectException(LogicException::class);

        (new SetFirmNotificationContext('firm-id', 1, 'notification-id', 'mail'))
            ->handle(new \stdClass, static function (): void {});
    }

    public function test_invitation_notification_payload_is_encrypted_and_uses_platform_queue(): void
    {
        $notification = new FirmInvitationNotification(
            plainTextToken: 'synthetic-plain-text-token',
            firmName: 'Synthetic Firm',
            expiresAt: 'tomorrow',
            inviterName: 'Synthetic Administrator',
        );

        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertSame('platform', $notification->queue);
        $this->assertTrue($notification->afterCommit);
    }
}
