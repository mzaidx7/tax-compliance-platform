<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkItem;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a work item is explicitly recorded at high risk.
 *
 * This notification never assesses risk. It reports a risk level a member
 * already recorded through `SetWorkItemRiskStatus`.
 */
final class WorkItemHighRiskNotification extends FirmNotification
{
    public function __construct(
        string $firmId,
        int $recipientUserId,
        private readonly string $workItemId,
    ) {
        parent::__construct($firmId, $recipientUserId);
    }

    public function triggeringRecord(): WorkItem
    {
        return WorkItem::query()->findOrFail($this->workItemId);
    }

    public function templateKey(): string
    {
        return 'work_item_high_risk';
    }

    public function templateVersion(): int
    {
        return 1;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User || $notifiable->getKey() !== $this->recipientUserId()) {
            throw new AuthorizationException('The high risk notification recipient is invalid.');
        }

        $firm = app(FirmContext::class)->firm();
        $workItem = WorkItem::query()
            ->with('obligation.client')
            ->findOrFail($this->workItemId);

        return (new MailMessage)
            ->subject("Work recorded at high risk for {$firm->name}")
            ->greeting("Hello {$notifiable->name}")
            ->line("Work for {$workItem->obligation->client->internal_code} was recorded at high risk.")
            ->line("Obligation: {$workItem->obligation->obligation_type}.")
            ->line("Current work status: {$workItem->status->label()}.")
            ->action('Open work register', route('obligations.index'))
            ->line('Review the recorded reason in the work register and decide the next action.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
