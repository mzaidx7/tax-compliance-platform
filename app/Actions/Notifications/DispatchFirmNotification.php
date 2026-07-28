<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Actions\Audit\RecordAudit;
use App\Enums\FirmMembershipStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationRequestStatus;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Notifications\FirmNotification;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DispatchFirmNotification
{
    public function __construct(
        private FirmContext $firmContext,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(
        User $recipient,
        FirmNotification $notification,
        string $idempotencyKey,
        ?User $actor = null,
        ?CarbonImmutable $scheduledAt = null,
    ): NotificationRequest {
        $firm = $this->firmContext->firm();
        $this->assertActorMatchesContext($actor);
        $this->assertValidIdempotencyKey($idempotencyKey);

        if (
            $notification->firmId() !== $firm->getKey()
            || $notification->recipientUserId() !== $recipient->getKey()
        ) {
            throw new AuthorizationException('The notification does not match the active firm and recipient.');
        }

        $recipientIsActive = FirmMembership::query()
            ->where('user_id', $recipient->getKey())
            ->where('status', FirmMembershipStatus::Active)
            ->exists();

        if (! $recipientIsActive) {
            throw new AuthorizationException('Only active firm members may receive operational notifications.');
        }

        $channel = $this->channel($notification, $recipient);
        $trigger = $notification->triggeringRecord();
        $this->assertTriggerMatchesContext($trigger);
        $deterministicKey = $this->deterministicKey(
            notification: $notification,
            recipient: $recipient,
            channel: $channel,
            idempotencyKey: $idempotencyKey,
            trigger: $trigger,
        );
        $effectiveScheduledAt = $scheduledAt ?? Date::now()->toImmutable();

        return DB::transaction(function () use (
            $actor,
            $channel,
            $deterministicKey,
            $effectiveScheduledAt,
            $notification,
            $recipient,
            $trigger,
        ): NotificationRequest {
            $request = NotificationRequest::query()->firstOrCreate(
                ['deterministic_key' => $deterministicKey],
                [
                    'recipient_user_id' => $recipient->getKey(),
                    'template_key' => $notification->templateKey(),
                    'template_version' => $notification->templateVersion(),
                    'channel' => $channel,
                    'trigger_type' => $trigger?->getMorphClass(),
                    'trigger_id' => $trigger === null ? null : (string) $trigger->getKey(),
                    'scheduled_at' => $effectiveScheduledAt,
                    'status' => NotificationRequestStatus::Queued,
                    'attempt_count' => 0,
                    'correlation_id' => (string) Str::ulid(),
                ],
            );

            if (! $request->wasRecentlyCreated) {
                $this->recordAudit->handle(
                    action: 'firm.notification.duplicate_suppressed',
                    actor: $actor,
                    auditable: $request,
                    after: [
                        'notification_request_id' => $request->getKey(),
                        'deterministic_key' => $request->deterministic_key,
                    ],
                    correlationId: $request->correlation_id,
                );

                return $request;
            }

            $notification->trackRequest($request->id);

            $this->recordAudit->handle(
                action: 'firm.notification.queued',
                actor: $actor,
                auditable: $request,
                after: [
                    'notification_request_id' => $request->getKey(),
                    'recipient_user_id' => $recipient->getKey(),
                    'template_key' => $notification->templateKey(),
                    'template_version' => $notification->templateVersion(),
                    'channel' => $channel->value,
                    'deterministic_key' => $request->deterministic_key,
                ],
                correlationId: $request->correlation_id,
            );

            if ($effectiveScheduledAt->isFuture()) {
                $notification->delay($effectiveScheduledAt);
            }

            Notification::send($recipient, $notification);

            return $request;
        });
    }

    private function assertValidIdempotencyKey(string $idempotencyKey): void
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,199}\z/', $idempotencyKey) !== 1) {
            throw new InvalidArgumentException(
                'Notification idempotency keys must use 1 to 200 safe characters.',
            );
        }
    }

    private function assertTriggerMatchesContext(?Model $trigger): void
    {
        if ($trigger === null) {
            return;
        }

        if ((string) $trigger->getAttribute('firm_id') !== $this->firmContext->firmId()) {
            throw new AuthorizationException('The notification trigger does not belong to the active firm.');
        }
    }

    private function channel(FirmNotification $notification, User $recipient): NotificationChannel
    {
        $channels = $notification->via($recipient);

        if (count($channels) !== 1) {
            throw new InvalidArgumentException('Tracked firm notifications require exactly one supported channel.');
        }

        return NotificationChannel::tryFrom($channels[0])
            ?? throw new InvalidArgumentException('The firm notification channel is not supported.');
    }

    private function deterministicKey(
        FirmNotification $notification,
        User $recipient,
        NotificationChannel $channel,
        string $idempotencyKey,
        ?Model $trigger,
    ): string {
        return hash('sha256', implode('|', [
            'firm-notification:v1',
            $notification->firmId(),
            (string) $recipient->getKey(),
            $notification->templateKey(),
            (string) $notification->templateVersion(),
            $channel->value,
            $trigger?->getMorphClass() ?? 'none',
            $trigger === null ? 'none' : (string) $trigger->getKey(),
            $idempotencyKey,
        ]));
    }

    private function assertActorMatchesContext(?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $membership = $this->firmContext->membership();

        if ($membership === null || $membership->user_id !== $actor->getKey()) {
            throw new AuthorizationException('The notification actor does not match the active firm membership.');
        }
    }
}
