<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Messages\MailMessage;

final class ManagerOperationalSummaryNotification extends FirmNotification
{
    public function __construct(
        string $firmId,
        int $recipientUserId,
        private readonly int $dueSoon,
        private readonly int $overdue,
        private readonly int $highRisk,
        private readonly int $overduePayments,
        private readonly string $generatedOn,
    ) {
        parent::__construct($firmId, $recipientUserId);
    }

    public function templateKey(): string
    {
        return 'manager_operational_summary';
    }

    public function templateVersion(): int
    {
        return 1;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User || $notifiable->getKey() !== $this->recipientUserId()) {
            throw new AuthorizationException('The manager summary recipient is invalid.');
        }
        $firm = app(FirmContext::class)->firm();

        return (new MailMessage)
            ->subject("Recorded operational summary for {$firm->name}")
            ->greeting("Hello {$notifiable->name}")
            ->line("This summary reflects stored operational states on {$this->generatedOn}.")
            ->line("Open obligations due in 30 days: {$this->dueSoon}.")
            ->line("Open obligations overdue: {$this->overdue}.")
            ->line("Active work recorded high risk: {$this->highRisk}.")
            ->line("Payments recorded overdue: {$this->overduePayments}.")
            ->action('Open operational dashboard', route('dashboard'))
            ->line('These counts do not calculate or guarantee compliance.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
