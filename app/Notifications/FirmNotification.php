<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\Notifications\FinalizeFailedNotificationRequest;
use App\Enums\FirmMembershipStatus;
use App\Models\FirmMembership;
use App\Models\User;
use App\Notifications\Middleware\SetFirmNotificationContext;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use LogicException;
use Throwable;

abstract class FirmNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** Protected for the same queue-serialization reason as the identity below. */
    protected ?string $notificationRequestId = null;

    /**
     * These are protected and not readonly so a subclass that declares its own
     * constructor survives queue serialization. A parent's private properties
     * are invisible to reflection on the subclass, and PHP forbids initializing
     * a parent's readonly property from a subclass scope, so either modifier
     * would leave them uninitialized when the queued job is rebuilt.
     *
     * They are never reassigned. Identity is exposed only through the final
     * accessors below, and no setter exists.
     */
    public function __construct(
        protected string $firmId,
        protected int $recipientUserId,
    ) {
        if ($firmId === '' || $recipientUserId < 1) {
            throw new InvalidArgumentException('Firm notifications require valid firm and recipient identities.');
        }

        $this->onQueue((string) config('platform.queue.name', 'platform'));
        $this->afterCommit();
    }

    final public function firmId(): string
    {
        return $this->firmId;
    }

    final public function recipientUserId(): int
    {
        return $this->recipientUserId;
    }

    abstract public function templateKey(): string;

    abstract public function templateVersion(): int;

    public function triggeringRecord(): ?Model
    {
        return null;
    }

    /**
     * @return list<string>
     */
    abstract public function via(object $notifiable): array;

    final public function trackRequest(string $notificationRequestId): void
    {
        if ($notificationRequestId === '' || $this->notificationRequestId !== null) {
            throw new LogicException('A firm notification must be tracked by exactly one request.');
        }

        $this->notificationRequestId = $notificationRequestId;
    }

    final public function notificationRequestId(): ?string
    {
        return $this->notificationRequestId;
    }

    /**
     * @return list<int>
     */
    final public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return list<object>
     */
    final public function middleware(object $notifiable, string $channel): array
    {
        if ($this->notificationRequestId === null) {
            throw new LogicException('Firm notifications must use the tracked dispatcher before queueing.');
        }

        if (! $notifiable instanceof User || $notifiable->getKey() !== $this->recipientUserId) {
            throw new AuthorizationException('The firm notification recipient does not match its queued identity.');
        }

        return [
            new SetFirmNotificationContext(
                $this->firmId,
                $this->recipientUserId,
                $this->notificationRequestId,
                $channel,
            ),
        ];
    }

    final public function shouldSend(object $notifiable, string $channel): bool
    {
        $context = app(FirmContext::class);

        if (
            ! $notifiable instanceof User
            || $notifiable->getKey() !== $this->recipientUserId
            || ! $context->hasFirm()
            || $context->firmId() !== $this->firmId
        ) {
            return false;
        }

        return FirmMembership::query()
            ->where('user_id', $this->recipientUserId)
            ->where('status', FirmMembershipStatus::Active)
            ->exists();
    }

    final public function failed(Throwable $exception): void
    {
        if ($this->notificationRequestId === null) {
            return;
        }

        app(FinalizeFailedNotificationRequest::class)->handle(
            firmId: $this->firmId,
            notificationRequestId: $this->notificationRequestId,
            exception: $exception,
        );
    }
}
